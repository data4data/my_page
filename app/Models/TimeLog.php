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
                $log->duration_minutes = (int) round(
                    ($log->ended_at->timestamp - $log->started_at->timestamp) / 60
                );
            }
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
