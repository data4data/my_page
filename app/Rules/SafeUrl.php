<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A link the public page may put in an href.
 *
 * Not Laravel's `url` rule, which rejects page fragments like "#work" and
 * relative paths — both of which the editor legitimately produces.
 *
 * Only the scheme matters: these go straight into :href, so a stored
 * "javascript:..." would run for every visitor. No scheme at all can only
 * point back at this site, so it is allowed.
 */
class SafeUrl implements ValidationRule
{
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    // Not parse_url(): it returns nothing for values a browser still acts on.
    private const SCHEME_PATTERN = '/^\s*([a-z][a-z0-9+.\-]*)\s*:/i';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        // Browsers strip control characters first, so "java\nscript:..."
        // would still run. Simpler to refuse them than to emulate that.
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $fail('The :attribute must not contain control characters.');

            return;
        }

        if (! preg_match(self::SCHEME_PATTERN, $value, $matches)) {
            return;
        }

        if (! in_array(strtolower($matches[1]), self::ALLOWED_SCHEMES, true)) {
            $fail('The :attribute must be a link, an email address or a phone number.');
        }
    }
}
