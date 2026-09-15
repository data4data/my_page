<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeLog extends Model
{
    protected $fillable = [
        'task_id',
        'started_at',
        'ended_at',
        'duration_minutes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Stored, not derived on read, so report totals are one SUM().
        static::saving(function (TimeLog $log): void {
            if ($log->started_at && $log->ended_at) {
                // Floored at 0: the column is unsigned, so a backwards pair
                // would error.
                $log->duration_minutes = max(0, (int) round(
                    ($log->ended_at->timestamp - $log->started_at->timestamp) / 60
                ));
            } elseif (! $log->ended_at) {
                // Reopened: the old duration no longer describes anything.
                $log->duration_minutes = null;
            }
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
