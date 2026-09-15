<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Carbon;

class TimerService
{
    /**
     * Starts a time log and sets the task to in_progress. If one is already
     * running for this task, returns the task unchanged.
     */
    public function start(Task $task): Task
    {
        $running = $task->timeLogs()->whereNull('ended_at')->first();

        if (! $running) {
            // Only one timer runs at a time, or the same minutes count
            // against several tasks and every report total overstates the day.
            // Paused, not done: it was handed over, not finished.
            $this->pauseOtherRunningTasks($task->user_id, $task->id);

            $task->timeLogs()->create(['started_at' => Carbon::now()]);
            $task->update(['status' => TaskStatus::InProgress]);
        }

        return $task->fresh(['category', 'timeLogs']);
    }

    /** Does nothing if no timer is running, so it is safe to call twice. */
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
            // One model at a time: a mass update would skip TimeLog's saving
            // hook, which is what computes duration_minutes.
            foreach ($other->timeLogs as $log) {
                $log->update(['ended_at' => Carbon::now()]);
            }

            $other->update(['status' => TaskStatus::Paused]);
        }
    }
}
