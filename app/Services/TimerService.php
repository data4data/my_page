<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Carbon;

class TimerService
{
    /**
     * Starts a fresh time log for the task and flips it to in_progress. If one
     * is already running for this task, it's returned as-is instead of
     * starting a second concurrent log.
     */
    public function start(Task $task): Task
    {
        $running = $task->timeLogs()->whereNull('ended_at')->first();

        if (! $running) {
            // Only one timer may run at a time, or the same minutes would be
            // counted against several tasks at once and every report total
            // would overstate the day. Whatever was running gets stopped and
            // parked as paused (not done — it wasn't finished, just handed over).
            $this->pauseOtherRunningTasks($task->user_id, $task->id);

            $task->timeLogs()->create(['started_at' => Carbon::now()]);
            $task->update(['status' => TaskStatus::InProgress]);
        }

        return $task->fresh(['category', 'timeLogs']);
    }

    /**
     * No-op (not an error) if nothing is running — stop is safe to call even
     * if the timer was already stopped elsewhere.
     */
    public function stop(Task $task): Task
    {
        $running = $task->timeLogs()->whereNull('ended_at')->latest('started_at')->first();
        $running?->update(['ended_at' => Carbon::now()]);

        return $task->fresh(['category', 'timeLogs']);
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
