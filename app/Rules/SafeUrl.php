<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A link the public page is allowed to render into an href.
 *
 * Deliberately not Laravel's `url` rule: the seeded CTAs are page fragments
 * ("#work") and the editor legitimately produces relative paths, both of
 * which `url` rejects — that would be the same trap UpdatePortfolioRequest's
 * docblock describes, a rule stricter than the UI it validates.
 *
 * What actually matters is the scheme. PublicPage.vue binds these values
 * straight into :href, so "javascript:..." stored here executes for every
 * visitor to the public page. A value with no scheme can only point back at
 * this site, so it is left alone.
 */
class SafeUrl implements ValidationRule
{
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    // Mirrors how a browser finds a scheme, rather than parse_url(), which
    // returns nothing for values a browser would still act on.
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

        // Browsers strip control characters before resolving a URL, so
        // "java\nscript:..." runs even though it does not look like a scheme
        // here. Refusing them outright is simpler than emulating that.
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
