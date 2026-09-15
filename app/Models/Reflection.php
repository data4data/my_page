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
     * The one note a user has for a period, matched the way the unique index
     * defines it.
     *
     * whereDate(), not updateOrCreate(): the 'date' cast stores these with a
     * time component, and updateOrCreate compares the raw "Y-m-d" input
     * against it — so it never finds the row and hits the unique constraint.
     */
    public function scopeForPeriod(Builder $query, int $userId, string $periodType, string $periodStart, string $periodEnd): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('period_type', $periodType)
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
