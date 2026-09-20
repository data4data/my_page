<?php

namespace App\Http\Requests;

use App\Rules\SafeUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checks types and lengths, not presence: the editor lets a field be cleared.
 * Presence is demanded only where the column is NOT NULL.
 */
class UpdatePortfolioRequest extends FormRequest
{
    private const TEXT_MAX = 5000;

    private const TRANSLATED_PROFILE_FIELDS = [
        'role',
        'headline',
        'summary',
        'primary_cta_label',
        'secondary_cta_label',
        'location_note',
        'availability_note',
        'quote',
        'quote_author',
    ];

    // Translated child fields, split by whether the column accepts null.
    private const REQUIRED_CHILD_TEXT = [
        'metrics.*.label',
        'expertise_items.*.title',
        'expertise_items.*.description',
        'projects.*.title',
        'projects.*.summary',
        'process_steps.*.title',
    ];

    private const OPTIONAL_CHILD_TEXT = [
        'projects.*.result',
        'process_steps.*.description',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'profile' => ['required', 'array'],
            'profile.initials' => ['sometimes', 'string', 'max:12'],
            'profile.default_language' => ['sometimes', Rule::in(['en', 'nl'])],
            'profile.show_language_toggle' => ['sometimes', 'boolean'],
            // `tone` is closed: each value is a CSS class (.headline-blue / -gold).
            'profile.headline_highlights' => ['nullable', 'array'],
            'profile.headline_highlights.*.text' => ['required', 'string', 'max:60'],
            'profile.headline_highlights.*.tone' => ['required', Rule::in(['blue', 'gold'])],
            // SafeUrl, not 'url': the seeded CTAs are fragments like "#work".
            'profile.primary_cta_url' => ['nullable', 'string', 'max:255', new SafeUrl],
            'profile.secondary_cta_url' => ['nullable', 'string', 'max:255', new SafeUrl],

            // The page turns this into a mailto:, so it is an address, not a URL.
            'profile.contact_email' => ['nullable', 'string', 'email', 'max:190'],

            // Absolute http(s): a crawler on another host has to fetch it.
            'profile.social_image_url' => ['nullable', 'string', 'url:http,https', 'max:255'],

            'social_links' => ['array'],
            'social_links.*.label' => ['nullable', 'string', 'max:60'],
            'social_links.*.url' => ['required', 'string', 'max:255', new SafeUrl],
            // Resolved via iconMap; an unknown key renders nothing.
            'social_links.*.icon' => ['nullable', 'string', 'max:60'],
            // Independent placements; is_visible is derived from them by the DB.
            'social_links.*.in_rail' => ['sometimes', 'boolean'],
            'social_links.*.in_footer' => ['sometimes', 'boolean'],

            'metrics' => ['array'],
            // NOT NULL, and a cleared field arrives as null: 422 beats a 500.
            'metrics.*.value' => ['required', 'string', 'max:24'],
            'metrics.*.is_visible' => ['sometimes', 'boolean'],

            'expertise_items' => ['array'],
            'expertise_items.*.icon' => ['nullable', 'string', 'max:60'],
            'expertise_items.*.category' => ['nullable', 'string', 'max:255'],
            'expertise_items.*.is_visible' => ['sometimes', 'boolean'],

            'projects' => ['array'],
            'projects.*.tags' => ['nullable', 'array'],
            'projects.*.tags.*' => ['string', 'max:60'],
            'projects.*.visual_style' => ['nullable', 'string', 'max:255'],
            'projects.*.is_visible' => ['sometimes', 'boolean'],

            'process_steps' => ['array'],
            'process_steps.*.group' => ['nullable', 'string', 'max:255'],
            'process_steps.*.icon' => ['nullable', 'string', 'max:60'],
            'process_steps.*.is_visible' => ['sometimes', 'boolean'],
        ];

        foreach (self::TRANSLATED_PROFILE_FIELDS as $field) {
            $rules += $this->translatedRules("profile.{$field}", required: false);
        }

        foreach (self::REQUIRED_CHILD_TEXT as $field) {
            $rules += $this->translatedRules($field, required: true);
        }

        foreach (self::OPTIONAL_CHILD_TEXT as $field) {
            $rules += $this->translatedRules($field, required: false);
        }

        return $rules;
    }

    /** Every free-text field is an {en, nl} pair; either side may be empty. */
    private function translatedRules(string $key, bool $required): array
    {
        return [
            $key => [$required ? 'required' : 'nullable', 'array'],
            "{$key}.en" => ['nullable', 'string', 'max:'.self::TEXT_MAX],
            "{$key}.nl" => ['nullable', 'string', 'max:'.self::TEXT_MAX],
        ];
    }
}
