<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Events\TimerStarted;
use App\Events\TimerStopped;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TimerService
{
    /** A timer already running for this task returns it unchanged. */
    public function start(Task $task): Task
    {
        $started = false;

        $task = DB::transaction(function () use ($task, &$started) {
            $this->lockOwner($task->user_id);

            $running = $task->timeLogs()->whereNull('ended_at')->exists();

            if (! $running) {
                // Only one timer at a time, or the same minutes count against
                // several tasks and every report total overstates the day.
                $this->pauseOtherRunningTasks($task->user_id, $task->id);

                // user_id too: the one-running-timer index is built on it.
                $task->timeLogs()->create([
                    'user_id' => $task->user_id,
                    'started_at' => Carbon::now(),
                ]);
                $task->update(['status' => TaskStatus::InProgress]);
                $started = true;
            }

            return $task->fresh(['category', 'timeLogs']);
        });

        // Only when a timer actually opened: a no-op is not news.
        if ($started) {
            TimerStarted::dispatch($task);
        }

        return $task;
    }

    /** Does nothing if no timer is running, so it is safe to call twice. */
    public function stop(Task $task): Task
    {
        $stopped = false;

        $task = DB::transaction(function () use ($task, &$stopped) {
            $this->lockOwner($task->user_id);

            $running = $task->timeLogs()->whereNull('ended_at')->latest('started_at')->first();
            $running?->update(['ended_at' => Carbon::now()]);
            $stopped = $running !== null;

            return $task->fresh(['category', 'timeLogs']);
        });

        if ($stopped) {
            TimerStopped::dispatch($task);
        }

        return $task;
    }

    /**
     * Serialises every timer change for one owner, so two clicks landing
     * together cannot both open a log. The lock is on the user, not the task:
     * the rule is per-user, and two tasks would take two different locks.
     */
    private function lockOwner(int $userId): void
    {
        User::query()->whereKey($userId)->lockForUpdate()->first();
    }

    private function pauseOtherRunningTasks(int $userId, int $exceptTaskId): void
    {
        $others = Task::query()
            ->where('user_id', $userId)
            ->whereKeyNot($exceptTaskId)
            ->whereHas('timeLogs', fn ($query) => $query->whereNull('ended_at'))
            ->with(['timeLogs' => fn ($query) => $query->whereNull('ended_at')])
            ->get();

        $now = Carbon::now();

        foreach ($others as $other) {
            foreach ($other->timeLogs as $log) {
                $log->update(['ended_at' => $now]);
            }

            $other->update(['status' => TaskStatus::Paused]);
        }
    }
}
