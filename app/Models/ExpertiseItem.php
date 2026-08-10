<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpertiseItem extends Model
{
    protected $fillable = [
        'portfolio_profile_id',
        'title',
        'description',
        'icon',
        'category',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'is_visible' => 'boolean',
    ];
}
