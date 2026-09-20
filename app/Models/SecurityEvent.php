<?php

namespace App\Models;

use App\Enums\SecurityEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Every sign-in attempt. Written once, so no updated_at; rows expire, because
 * they hold IP addresses.
 */
class SecurityEvent extends Model
{
    use MassPrunable;

    const UPDATED_AT = null;

    public const RETENTION_DAYS = 30;

    protected $fillable = [
        'type',
        'ip_address',
        'email',
        'user_id',
        'user_agent',
    ];

    protected $casts = [
        'type' => SecurityEventType::class,
    ];

    /** Deleted by `php artisan model:prune`, scheduled daily in routes/console.php. */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', Carbon::now()->subDays(self::RETENTION_DAYS));
    }

    public function scopeSince(Builder $query, Carbon $moment): Builder
    {
        return $query->where('created_at', '>=', $moment);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
