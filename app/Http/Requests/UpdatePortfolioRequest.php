<?php

namespace App\Http\Requests;

use App\Enums\VisualStyle;
use App\Rules\SafeUrl;
use App\Support\PortfolioFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checks types and lengths, not presence: the editor lets a field be cleared.
 * Presence is demanded only where the column is NOT NULL.
 *
 * The five child collections are the exception. Saving replaces each one
 * whole, so a payload that merely *omits* one would delete every row in it —
 * `present` makes a half-built payload a 422 instead of a silent wipe. Not
 * `required`, which rejects an empty array: having no projects is allowed,
 * forgetting to mention them is not.
 */
class UpdatePortfolioRequest extends FormRequest
{
    private const TEXT_MAX = 5000;

    /**
     * The translated child fields whose column accepts null. Everything else
     * in PortfolioFields::TRANSLATED_CHILDREN is NOT NULL, so listing the
     * short exception keeps the field lists themselves in one place.
     *
     * @var array<string, list<string>>
     */
    private const OPTIONAL_CHILD_TEXT = [
        'projects' => ['result'],
        'process_steps' => ['description'],
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

            'social_links' => ['present', 'array'],
            'social_links.*.label' => ['nullable', 'string', 'max:60'],
            'social_links.*.url' => ['required', 'string', 'max:255', new SafeUrl],
            // Resolved via iconMap; an unknown key renders nothing.
            'social_links.*.icon' => ['nullable', 'string', 'max:60'],
            // Independent placements; is_visible is derived from them by the DB.
            'social_links.*.in_rail' => ['sometimes', 'boolean'],
            'social_links.*.in_footer' => ['sometimes', 'boolean'],

            'metrics' => ['present', 'array'],
            // NOT NULL, and a cleared field arrives as null: 422 beats a 500.
            'metrics.*.value' => ['required', 'string', 'max:24'],
            'metrics.*.is_visible' => ['sometimes', 'boolean'],

            'expertise_items' => ['present', 'array'],
            'expertise_items.*.icon' => ['nullable', 'string', 'max:60'],
            'expertise_items.*.category' => ['nullable', 'string', 'max:255'],
            'expertise_items.*.is_visible' => ['sometimes', 'boolean'],

            'projects' => ['present', 'array'],
            'projects.*.tags' => ['nullable', 'array'],
            'projects.*.tags.*' => ['string', 'max:60'],
            // A list, not free text: an unknown style draws a blank panel.
            // Required like metrics.*.value, because the column is NOT NULL
            // and ConvertEmptyStringsToNull would otherwise reach the insert.
            'projects.*.visual_style' => ['required', Rule::enum(VisualStyle::class)],
            'projects.*.is_visible' => ['sometimes', 'boolean'],

            'process_steps' => ['present', 'array'],
            'process_steps.*.group' => ['nullable', 'string', 'max:255'],
            'process_steps.*.icon' => ['nullable', 'string', 'max:60'],
            'process_steps.*.is_visible' => ['sometimes', 'boolean'],
        ];

        foreach (PortfolioFields::TRANSLATED_PROFILE as $field) {
            $rules += $this->translatedRules("profile.{$field}", required: false);
        }

        foreach (PortfolioFields::TRANSLATED_CHILDREN as $collection => $fields) {
            $optional = self::OPTIONAL_CHILD_TEXT[$collection] ?? [];

            foreach ($fields as $field) {
                $rules += $this->translatedRules(
                    "{$collection}.*.{$field}",
                    required: ! in_array($field, $optional, true),
                );
            }
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
