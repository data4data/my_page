<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessStep extends Model
{
    protected $fillable = [
        'portfolio_profile_id',
        'group',
        'title',
        'description',
        'icon',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'is_visible' => 'boolean',
    ];
}
