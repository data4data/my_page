<?php

namespace App\Http\Requests;

use App\Rules\SafeUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Replaces the shape-only check the controller used to do, which asked "is
 * profile an array?" and then wrote fifteen fields plus four whole collections
 * without looking inside any of them.
 *
 * These rules mostly check **types and lengths, not presence**. The admin UI
 * lets a field be cleared, so a 'required' rule on free text would reject
 * payloads the editor legitimately produces — a worse bug than the one being
 * fixed. Presence is demanded only where the column is NOT NULL, because
 * ConvertEmptyStringsToNull turns a cleared field into null and the insert
 * would then fail with a 500 instead of a readable 422.
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
            // SafeUrl, not 'url': the seeded CTAs are fragments ("#work") and
            // relative paths are valid here too. These three land in :href on
            // the public page, which is why the scheme is checked at all.
            'profile.primary_cta_url' => ['nullable', 'string', 'max:255', new SafeUrl],
            'profile.secondary_cta_url' => ['nullable', 'string', 'max:255', new SafeUrl],
            'profile.social_links' => ['nullable', 'array'],
            'profile.social_links.*.label' => ['nullable', 'string', 'max:60'],
            'profile.social_links.*.url' => ['required', 'string', 'max:255', new SafeUrl],
            // Resolved through iconMap in resources/js/shared/icons.js; an
            // unknown key renders nothing rather than failing.
            'profile.social_links.*.icon' => ['nullable', 'string', 'max:60'],

            'metrics' => ['array'],
            // Required, unlike the translated text below: the column is NOT
            // NULL, and Laravel's ConvertEmptyStringsToNull middleware turns a
            // cleared field into null before it ever reaches here. Without this
            // rule that null reaches the insert and becomes a 500 — which is
            // what the old shape-only validation allowed.
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
     * Every free-text field is a {en, nl} pair stored in a JSON column. The
     * pair itself must be an array; each side may be empty but must be text,
     * which is what stops a plain string or a nested object being written
     * where the frontend expects .en / .nl.
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
