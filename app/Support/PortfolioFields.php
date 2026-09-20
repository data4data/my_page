<?php

namespace App\Support;

/**
 * What the public page is made of. One list per shape, shared by the read and
 * the write so the two cannot disagree about which fields exist.
 */
final class PortfolioFields
{
    /** @var list<string> */
    public const PROFILE = [
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

    /**
     * Social links carry no `is_visible`: theirs is a generated column derived
     * from the two placements, so it is neither written nor published.
     *
     * @var array<string, list<string>>
     */
    public const CHILDREN = [
        'metrics' => ['value', 'label', 'is_visible'],
        'expertiseItems' => ['title', 'description', 'icon', 'category', 'is_visible'],
        'projects' => ['title', 'summary', 'result', 'tags', 'visual_style', 'is_visible'],
        'processSteps' => ['group', 'title', 'description', 'icon', 'is_visible'],
        'socialLinks' => ['label', 'url', 'icon', 'in_rail', 'in_footer'],
    ];

    /**
     * Payload keys (snake_case) -> relation names on PortfolioProfile.
     *
     * @var array<string, string>
     */
    public const RELATIONS = [
        'metrics' => 'metrics',
        'expertise_items' => 'expertiseItems',
        'projects' => 'projects',
        'process_steps' => 'processSteps',
        'social_links' => 'socialLinks',
    ];
}
