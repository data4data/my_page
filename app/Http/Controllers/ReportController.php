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
    // ?period_type=week|month&period_start=YYYY-MM-DD — period_start is
    // expected to already be that period's first day (the frontend computes
    // it client-side); the period's own end is derived here from that.
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
            ->with(['category', 'timeLogs'])
            ->get();

        $byCategory = $tasks
            ->groupBy(fn (Task $task) => $task->category?->name ?? 'Uncategorized')
            ->map(fn ($group, $name) => [
                'category' => $name,
                'color' => $group->first()->category?->color ?? '#9b9b9b',
                'minutes' => $group->flatMap->timeLogs->sum('duration_minutes'),
                'planned_minutes' => $group->sum(fn (Task $task) => $task->plannedMinutes()),
                'tasks' => $group->count(),
            ])
            ->values();

        $byStatus = $tasks
            ->groupBy(fn (Task $task) => $task->status->value)
            ->map->count();

        return response()->json([
            'period_type' => $periodType->value,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'total_minutes' => $tasks->flatMap->timeLogs->sum('duration_minutes'),
            'total_planned_minutes' => $tasks->sum(fn (Task $task) => $task->plannedMinutes()),
            'task_count' => $tasks->count(),
            'by_category' => $byCategory,
            'by_status' => $byStatus,
        ]);
    }
}
