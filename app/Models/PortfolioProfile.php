<?php

namespace App\Models;

use App\Support\PortfolioFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

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
        'footer_note_left',
        'footer_note_right',
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
        'footer_note_left' => 'array',
        'footer_note_right' => 'array',
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

    /**
     * Replace one child collection whole, numbering the rows by the position
     * they arrive in. Both writers use this — a save submits complete
     * collection state, and the seeder writes the starting content the same
     * way, so neither can grow a rule the other does not have.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function replaceChildren(string $relation, array $items): void
    {
        $keys = PortfolioFields::CHILDREN[$relation];

        $this->{$relation}()->delete();

        foreach (array_values($items) as $index => $item) {
            $payload = Arr::only($item, $keys);
            $payload['sort_order'] = $index + 1;

            // Social links derive is_visible from their two placements, and
            // MySQL rejects an INSERT naming a generated column.
            if (in_array('is_visible', $keys, true)) {
                $payload['is_visible'] = $item['is_visible'] ?? true;
            }

            $this->{$relation}()->create($payload);
        }
    }

    /**
     * The eager-load map that puts every child collection in its saved order.
     *
     * @return array<string, \Closure>
     */
    public static function orderedChildren(): array
    {
        return array_map(
            fn (string $relation) => fn ($query) => $query->orderBy('sort_order'),
            array_combine(PortfolioFields::RELATIONS, PortfolioFields::RELATIONS),
        );
    }
}
