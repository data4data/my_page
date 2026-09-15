<?php

namespace App\Http\Requests;

use App\Rules\SafeUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checks types and lengths, not presence. The editor lets a field be cleared,
 * so `required` on free text would reject payloads it legitimately produces.
 *
 * Presence is demanded only where the column is NOT NULL, because
 * ConvertEmptyStringsToNull turns a cleared field into null and the insert
 * would fail with a 500 instead of a readable 422.
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
            // Which headline words take an accent colour. `tone` is closed
            // because each value is a CSS class (.headline-blue / -gold).
            'profile.headline_highlights' => ['nullable', 'array'],
            'profile.headline_highlights.*.text' => ['required', 'string', 'max:60'],
            'profile.headline_highlights.*.tone' => ['required', Rule::in(['blue', 'gold'])],
            // SafeUrl, not 'url': the seeded CTAs are fragments like "#work".
            // These land in :href on the public page.
            'profile.primary_cta_url' => ['nullable', 'string', 'max:255', new SafeUrl],
            'profile.secondary_cta_url' => ['nullable', 'string', 'max:255', new SafeUrl],
            'profile.social_links' => ['nullable', 'array'],
            'profile.social_links.*.label' => ['nullable', 'string', 'max:60'],
            'profile.social_links.*.url' => ['required', 'string', 'max:255', new SafeUrl],
            // Resolved via iconMap; an unknown key renders nothing.
            'profile.social_links.*.icon' => ['nullable', 'string', 'max:60'],
            // is_visible is the single switch the two placements replaced,
            // still accepted from older payloads.
            'profile.social_links.*.in_rail' => ['sometimes', 'boolean'],
            'profile.social_links.*.in_footer' => ['sometimes', 'boolean'],
            'profile.social_links.*.is_visible' => ['sometimes', 'boolean'],

            'metrics' => ['array'],
            // Required because the column is NOT NULL and a cleared field
            // arrives here as null. Without this the insert 500s.
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

    /**
     * Every free-text field is an {en, nl} pair in a JSON column. Each side
     * may be empty but must be text, so a plain string or a nested object
     * cannot be written where the frontend expects .en / .nl.
     */
    private function translatedRules(string $key, bool $required): array
    {
        return [
            $key => [$required ? 'required' : 'nullable', 'array'],
            "{$key}.en" => ['nullable', 'string', 'max:'.self::TEXT_MAX],
            "{$key}.nl" => ['nullable', 'string', 'max:'.self::TEXT_MAX],
        ];
    }
}
