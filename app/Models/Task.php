<?php

namespace App\Models;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'planned_duration_minutes',
        'status',
        'sort_order',
        'result_notes',
        'source',
        'external_ref',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'status' => TaskStatus::class,
        'source' => TaskSource::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * How long the task was meant to take. Prefers the explicit
     * planned_duration_minutes, falling back to the scheduled start→end span
     * (tasks added through the calendar set times but not a duration), and 0
     * when the task is open-ended.
     */
    public function plannedMinutes(): int
    {
        if ($this->planned_duration_minutes !== null) {
            return $this->planned_duration_minutes;
        }

        if (! $this->end_datetime || ! $this->start_datetime) {
            return 0;
        }

        // Raw timestamps rather than diffInMinutes(), whose sign/abs handling
        // varies by Carbon version — same reason TimeLog::booted() does it.
        return max(0, (int) round(($this->end_datetime->timestamp - $this->start_datetime->timestamp) / 60));
    }
}
