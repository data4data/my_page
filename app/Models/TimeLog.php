<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeLog extends Model
{
    /**
     * No `duration_minutes` or `running_user_id`: both are generated columns,
     * and MySQL rejects an INSERT that names one.
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
        // Denormalised from the task, and the one-running-timer index is built
        // on it. Filled here so a caller that forgets cannot index a log
        // against the wrong owner.
        static::creating(function (TimeLog $log): void {
            if ($log->user_id === null && $log->task_id !== null) {
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
