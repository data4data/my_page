<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TimerService
{
    /**
     * Starts a time log and sets the task to in_progress. If one is already
     * running for this task, returns the task unchanged.
     */
    public function start(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            $this->lockOwner($task->user_id);

            $running = $task->timeLogs()->whereNull('ended_at')->exists();

            if (! $running) {
                // Only one timer runs at a time, or the same minutes count
                // against several tasks and every report total overstates the
                // day. Paused, not done: it was handed over, not finished.
                $this->pauseOtherRunningTasks($task->user_id, $task->id);

                // user_id as well as the relation's task_id: it is what
                // the database's one-running-timer index is built on.
                $task->timeLogs()->create([
                    'user_id' => $task->user_id,
                    'started_at' => Carbon::now(),
                ]);
                $task->update(['status' => TaskStatus::InProgress]);
            }

            return $task->fresh(['category', 'timeLogs']);
        });
    }

    /** Does nothing if no timer is running, so it is safe to call twice. */
    public function stop(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            $this->lockOwner($task->user_id);

            $running = $task->timeLogs()->whereNull('ended_at')->latest('started_at')->first();
            $running?->update(['ended_at' => Carbon::now()]);

            return $task->fresh(['category', 'timeLogs']);
        });
    }

    /**
     * Serialises every timer change for one owner.
     *
     * Without it `start()` is a read followed by a write: two clicks landing
     * together both see no open log, both pass the check, and both insert —
     * leaving two timers running and double-counting every minute from then
     * on. The check has to happen with the write already guarded, which is
     * what the row lock plus the surrounding transaction buy.
     *
     * The lock is taken on the *user*, not the task, because the rule is
     * per-user: two different tasks started at the same instant would take
     * two different task locks, and both would still open a log. The user row
     * is the one row every timer change for that owner has in common, and it
     * always exists — a `lockForUpdate()` on a query that matches nothing
     * takes no row lock, only an index gap lock, which is a far subtler thing
     * to depend on.
     *
     * Cheap in practice: one row, held for the few milliseconds the insert
     * takes, and this workspace has a single signed-in owner.
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
            // A mass update would do here now that duration_minutes is a
            // generated column, but one at a time keeps each log's updated_at
            // honest and the loop is over at most a handful of rows.
            foreach ($other->timeLogs as $log) {
                $log->update(['ended_at' => $now]);
            }

            $other->update(['status' => TaskStatus::Paused]);
        }
    }
}
