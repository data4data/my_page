<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TimeLogController extends Controller
{
    // Starts a fresh time log for the task and flips it to in_progress. If
    // one is already running for this task, it's returned as-is instead of
    // starting a second concurrent log.
    public function start(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $running = $task->timeLogs()->whereNull('ended_at')->first();

        if (! $running) {
            // Only one timer may run at a time, or the same minutes would be
            // counted against several tasks at once and every report total
            // would overstate the day. Whatever was running gets stopped and
            // parked as paused (not done — it wasn't finished, just handed over).
            $this->pauseOtherRunningTasks($request->user()->id, $task->id);

            $task->timeLogs()->create(['started_at' => Carbon::now()]);
            $task->update(['status' => TaskStatus::InProgress]);
        }

        return response()->json(['task' => $task->fresh(['category', 'timeLogs'])]);
    }

    // No-op (not an error) if nothing is running — stop is safe to call
    // even if the timer was already stopped elsewhere.
    public function stop(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $running = $task->timeLogs()->whereNull('ended_at')->latest('started_at')->first();
        $running?->update(['ended_at' => Carbon::now()]);

        return response()->json(['task' => $task->fresh(['category', 'timeLogs'])]);
    }

    private function pauseOtherRunningTasks(int $userId, int $exceptTaskId): void
    {
        $others = Task::query()
            ->where('user_id', $userId)
            ->whereKeyNot($exceptTaskId)
            ->whereHas('timeLogs', fn ($query) => $query->whereNull('ended_at'))
            ->with(['timeLogs' => fn ($query) => $query->whereNull('ended_at')])
            ->get();

        foreach ($others as $other) {
            // Saved one model at a time rather than a mass query update, so
            // TimeLog::booted()'s saving hook still computes duration_minutes.
            foreach ($other->timeLogs as $log) {
                $log->update(['ended_at' => Carbon::now()]);
            }

            $other->update(['status' => TaskStatus::Paused]);
        }
    }
}
