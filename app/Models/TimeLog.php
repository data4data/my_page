<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeLog extends Model
{
    /**
     * `duration_minutes` and `running_user_id` are missing on purpose: both
     * are generated columns, and MySQL rejects an INSERT that names one.
     *
     * `user_id` is denormalised from the task. It is what the database's
     * "one running timer per user" index is built on, so it has to be set
     * whenever a log is opened — TimerService does that.
     */
    protected $fillable = [
        'task_id',
        'user_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // user_id is denormalised from the task, and the database's
        // one-running-timer index is built on it — so it must never be left
        // unset and must never disagree with tasks.user_id.
        //
        // Filled here rather than at every call site: a caller that forgets
        // would otherwise get a NOT NULL violation, or worse, index a log
        // against the wrong owner. TimerService passes it anyway, so this
        // only queries when something else did not.
        static::creating(function (TimeLog $log): void {
            if ($log->user_id === null && $log->task_id !== null) {
                // The relation rather than a query would hydrate a whole Task
                // to read one column, and it is never already loaded here:
                // $task->timeLogs()->create() sets task_id, not the relation.
                $log->user_id = Task::query()->whereKey($log->task_id)->value('user_id');
            }
        });
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
