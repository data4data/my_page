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
    // period_start is expected to already be that period's first day; the
    // end is derived from it here.
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_type' => ['required', Rule::enum(ReflectionPeriodType::class)],
            'period_start' => ['required', 'date'],
        ]);

        $periodType = ReflectionPeriodType::from($data['period_type']);
        $start = Carbon::parse($data['period_start'])->startOfDay();
        $end = $periodType === ReflectionPeriodType::Week
            ? $start->copy()->endOfWeek()
            : $start->copy()->endOfMonth();

        $tasks = Task::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('start_datetime', [$start, $end])
            ->with('category')
            // duration_minutes is stored, so this sums in SQL.
            ->withSum('timeLogs as tracked_minutes', 'duration_minutes')
            ->get();

        $tracked = fn (Task $task) => (int) ($task->tracked_minutes ?? 0);

        // Grouped by id, not name: a global and a personal category can share
        // a name. `null` is the uncategorized bucket, labelled by the frontend.
        $categories = $tasks->pluck('category')->filter()->keyBy('id')->all();

        $byCategory = $tasks
            ->groupBy(fn (Task $task) => $task->category_id)
            ->map(function ($group, $categoryId) use ($tracked, $categories) {
                $category = $categories[$categoryId] ?? null;

                return [
                    'category_id' => $group->first()->category_id,
                    'category' => $category?->name,
                    'color' => $category->color ?? '#9b9b9b',
                    'minutes' => $group->sum($tracked),
                    'planned_minutes' => $group->sum(fn (Task $task) => $task->plannedMinutes()),
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
            'total_planned_minutes' => $tasks->sum(fn (Task $task) => $task->plannedMinutes()),
            'task_count' => $tasks->count(),
            'by_category' => $byCategory,
            'by_status' => $byStatus,
        ]);
    }
}
