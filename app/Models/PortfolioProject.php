<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioProject extends Model
{
    protected $fillable = [
        'portfolio_profile_id',
        'title',
        'summary',
        'result',
        'tags',
        'visual_style',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'title' => 'array',
        'summary' => 'array',
        'result' => 'array',
        'tags' => 'array',
        'is_visible' => 'boolean',
    ];
}
