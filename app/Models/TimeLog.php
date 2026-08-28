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
        // Kept as a stored column (not derived on read) so report totals
        // are one SUM() query instead of per-row date-diffing in PHP.
        static::saving(function (TimeLog $log): void {
            if ($log->started_at && $log->ended_at) {
                // Floored at 0, matching Task::plannedMinutes(): the column is
                // unsignedInteger, so a backwards pair would be rejected by
                // MySQL in strict mode and stored silently by SQLite.
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
