<?php

namespace App\Models;

use App\Enums\ReflectionPeriodType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reflection extends Model
{
    protected $fillable = [
        'user_id',
        'period_type',
        'period_start',
        'period_end',
        'notes',
    ];

    protected $casts = [
        'period_type' => ReflectionPeriodType::class,
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    /**
     * The single note a user has for one period, matched the way the unique
     * index defines it.
     *
     * whereDate() rather than Eloquent's updateOrCreate(): the 'date' cast
     * stores period_start/period_end with a time component, and
     * updateOrCreate's raw search array bypasses that cast and compares
     * against the plain "Y-m-d" input — so it would never find the existing
     * row and would hit the unique constraint instead. That subtlety is worth
     * exactly one home, which is why the controller's read and write paths
     * both come through here.
     */
    public function scopeForPeriod(Builder $query, int $userId, string $periodType, string $periodStart, string $periodEnd): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('period_type', $periodType)
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
