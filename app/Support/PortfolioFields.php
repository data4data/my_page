<?php

namespace App\Support;

/**
 * What the public page is made of.
 *
 * One list per shape, read by everything that has an opinion about the
 * content: the resources that publish it, the service that writes it, and the
 * request that validates it. Keeping it here rather than on any one of them
 * is what stops the read and the write disagreeing about which fields exist.
 *
 * **A field is published because it is editable.** The resources emit exactly
 * these keys, so `GET /portfolio` — which needs no login — can only ever hand
 * back what the editor can put there, and a column added later joins neither
 * list by accident.
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
     * Per child relation. Social links carry no `is_visible`: theirs is a
     * generated column derived from the two placements, so it is neither
     * written nor published — but payload() still filters on it, which is how
     * a link shown in neither place stays off the public page.
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
     * Payload keys (snake_case) -> relation names on PortfolioProfile, in the
     * order the payload presents them.
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
