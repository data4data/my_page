<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortfolioProfile extends Model
{
    protected $fillable = [
        'slug',
        'initials',
        'role',
        'headline',
        'headline_highlights',
        'summary',
        'primary_cta_label',
        'primary_cta_url',
        'secondary_cta_label',
        'secondary_cta_url',
        'contact_email',
        'social_image_url',
        'location_note',
        'availability_note',
        'quote',
        'quote_author',
        'default_language',
        'show_language_toggle',
    ];

    protected $casts = [
        'role' => 'array',
        'headline' => 'array',
        'headline_highlights' => 'array',
        'summary' => 'array',
        'primary_cta_label' => 'array',
        'secondary_cta_label' => 'array',
        'location_note' => 'array',
        'availability_note' => 'array',
        'quote' => 'array',
        'quote_author' => 'array',
        'show_language_toggle' => 'boolean',
    ];

    /** @return HasMany<PortfolioSocialLink, $this> */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(PortfolioSocialLink::class);
    }

    /** @return HasMany<PortfolioMetric, $this> */
    public function metrics(): HasMany
    {
        return $this->hasMany(PortfolioMetric::class);
    }

    /** @return HasMany<ExpertiseItem, $this> */
    public function expertiseItems(): HasMany
    {
        return $this->hasMany(ExpertiseItem::class);
    }

    /** @return HasMany<PortfolioProject, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(PortfolioProject::class);
    }

    /** @return HasMany<ProcessStep, $this> */
    public function processSteps(): HasMany
    {
        return $this->hasMany(ProcessStep::class);
    }

    /** @return HasMany<PortfolioRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PortfolioRevision::class);
    }
}
