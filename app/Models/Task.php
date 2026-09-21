<?php

namespace App\Models;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<TimeLog, $this> */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * The open log, if a timer is running on this task. At most one exists:
     * time_logs carries a UNIQUE index on running_user_id, so the database
     * refuses a second open log for one owner.
     *
     * The board only ever asks whether a timer is running and since when, so
     * this is what a task carries instead of its whole history — a month of
     * tasks with every log attached is a payload nothing reads.
     *
     * @return HasOne<TimeLog, $this>
     */
    public function runningTimeLog(): HasOne
    {
        return $this->hasOne(TimeLog::class)->whereNull('ended_at');
    }

    /**
     * Prefers planned_duration_minutes, falls back to the start→end span (the
     * calendar sets times but no duration), and is 0 when open-ended.
     */
    public function plannedMinutes(): int
    {
        if ($this->planned_duration_minutes !== null) {
            return $this->planned_duration_minutes;
        }

        if (! $this->end_datetime || ! $this->start_datetime) {
            return 0;
        }

        // Raw timestamps: diffInMinutes()'s sign handling varies by Carbon version.
        return max(0, (int) round(($this->end_datetime->timestamp - $this->start_datetime->timestamp) / 60));
    }
}
