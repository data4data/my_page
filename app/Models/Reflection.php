<?php

namespace App\Models;

use App\Enums\ReflectionPeriodType;
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
