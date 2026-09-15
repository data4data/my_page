<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioRevision extends Model
{
    // The table has created_at but no updated_at: a snapshot is written once.
    const UPDATED_AT = null;

    protected $fillable = [
        'portfolio_profile_id',
        'user_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /** @return BelongsTo<PortfolioProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(PortfolioProfile::class, 'portfolio_profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
