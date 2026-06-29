<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioMetric extends Model
{
    protected $fillable = [
        'portfolio_profile_id',
        'value',
        'label',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'label' => 'array',
        'is_visible' => 'boolean',
    ];
}
