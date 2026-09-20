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
        // period_end is a generated column — MySQL rejects an INSERT naming it.
        'notes',
    ];

    protected $casts = [
        'period_type' => ReflectionPeriodType::class,
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    /**
     * Matched on (user, type, start): period_end is derived from those by the
     * database. whereDate(), because the 'date' cast stores a time component
     * that a raw "Y-m-d" comparison would never match.
     */
    public function scopeForPeriod(Builder $query, int $userId, string $periodType, string $periodStart): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('period_type', $periodType)
            ->whereDate('period_start', $periodStart);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
