<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortfolioProfile extends Model
{
    protected $fillable = [
        'slug',
        'type',
        'is_active',
        'initials',
        'role',
        'headline',
        'summary',
        'primary_cta_label',
        'primary_cta_url',
        'secondary_cta_label',
        'secondary_cta_url',
        'location_note',
        'availability_note',
        'quote',
        'quote_author',
        'social_links',
        'default_language',
        'show_language_toggle',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'role' => 'array',
        'headline' => 'array',
        'summary' => 'array',
        'primary_cta_label' => 'array',
        'secondary_cta_label' => 'array',
        'location_note' => 'array',
        'availability_note' => 'array',
        'quote' => 'array',
        'quote_author' => 'array',
        'social_links' => 'array',
        'show_language_toggle' => 'boolean',
    ];

    public function metrics(): HasMany
    {
        return $this->hasMany(PortfolioMetric::class);
    }

    public function expertiseItems(): HasMany
    {
        return $this->hasMany(ExpertiseItem::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(PortfolioProject::class);
    }

    public function processSteps(): HasMany
    {
        return $this->hasMany(ProcessStep::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PortfolioRevision::class);
    }
}
