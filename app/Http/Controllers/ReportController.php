<?php

namespace App\Http\Controllers;

use App\Enums\ReflectionPeriodType;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    /**
     * Two different questions, answered over the same rows: what was planned
     * for this period, and what was actually tracked during it. A task and its
     * time logs can fall in different periods, so neither number may be read
     * off the other's row.
     */
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_type' => ['required', Rule::enum(ReflectionPeriodType::class)],
            'period_start' => ['required', 'date'],
        ]);

        $periodType = ReflectionPeriodType::from($data['period_type']);
        // Normalised rather than trusted: a mid-week date would otherwise
        // report from that day to Sunday and call it the week.
        $start = $periodType->startFor(Carbon::parse($data['period_start']));
        $end = $periodType->endFor($start);

        // The window above is wall-clock, which is what start_datetime holds,
        // so tasks compare against it directly. started_at is a real instant,
        // so the same window has to be read in the owner's own zone and
        // converted: a timer run at 00:30 on Monday in Amsterdam is stored as
        // 22:30 on Sunday, and counted raw its minutes fall in last week.
        // shiftTimezone keeps the digits and changes the zone; ->utc() then
        // converts. A zone whose clocks jump at midnight has no such instant,
        // and PHP moves it to the hour that does exist.
        $zone = $request->user()->timezone;
        $trackedFrom = $start->copy()->shiftTimezone($zone)->utc();
        $trackedTo = $end->copy()->shiftTimezone($zone)->utc();

        // Minutes counted by when they were tracked, not by when the task they
        // belong to was scheduled. Summing a task's whole history put last
        // month's minutes in this week's total.
        $loggedInPeriod = fn ($query) => $query->whereBetween('started_at', [$trackedFrom, $trackedTo]);

        $tasks = Task::query()
            ->where('user_id', $request->user()->id)
            // Scheduled here, tracked here, or both: a task worked on outside
            // its own week still owns the minutes it was given.
            ->where(fn ($query) => $query
                ->whereBetween('start_datetime', [$start, $end])
                ->orWhereHas('timeLogs', $loggedInPeriod))
            ->with('category')
            // duration_minutes is stored, so this sums in SQL.
            ->withSum(['timeLogs as tracked_minutes' => $loggedInPeriod], 'duration_minutes')
            ->get();

        $tracked = fn (Task $task) => (int) ($task->tracked_minutes ?? 0);

        // Zero for a task that merely collected minutes here: its plan belongs
        // to the period it was scheduled in, and is counted there.
        $planned = fn (Task $task) => $task->start_datetime?->between($start, $end)
            ? $task->plannedMinutes()
            : 0;

        // Grouped by id, not name: a global and a personal category can share
        // a name. `null` is the uncategorized bucket, labelled by the frontend.
        $categories = $tasks->pluck('category')->filter()->keyBy('id')->all();

        $byCategory = $tasks
            ->groupBy(fn (Task $task) => $task->category_id)
            ->map(function ($group, $categoryId) use ($tracked, $planned, $categories) {
                $category = $categories[$categoryId] ?? null;

                return [
                    'category_id' => $group->first()->category_id,
                    'category' => $category?->name,
                    'color' => $category->color ?? '#9b9b9b',
                    'minutes' => $group->sum($tracked),
                    'planned_minutes' => $group->sum($planned),
                    'tasks' => $group->count(),
                ];
            })
            ->values();

        $byStatus = $tasks
            ->groupBy(fn (Task $task) => $task->status->value)
            ->map->count();

        return response()->json([
            'period_type' => $periodType->value,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'total_minutes' => $tasks->sum($tracked),
            'total_planned_minutes' => $tasks->sum($planned),
            'task_count' => $tasks->count(),
            'by_category' => $byCategory,
            'by_status' => $byStatus,
        ]);
    }
}
