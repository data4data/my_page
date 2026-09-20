<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioSocialLink extends Model
{
    /**
     * `is_visible` is missing on purpose: it is a generated column derived
     * from the two placements, and MySQL rejects an INSERT that names one.
     */
    protected $fillable = [
        'portfolio_profile_id',
        'label',
        'url',
        'icon',
        'in_rail',
        'in_footer',
        'sort_order',
    ];

    protected $casts = [
        'in_rail' => 'boolean',
        'in_footer' => 'boolean',
        'is_visible' => 'boolean',
    ];

    /** @return BelongsTo<PortfolioProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(PortfolioProfile::class, 'portfolio_profile_id');
    }
}
