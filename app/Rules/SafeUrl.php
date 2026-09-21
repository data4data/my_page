<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A link the public page may put in an href. Not Laravel's `url` rule, which
 * rejects the fragments and relative paths the editor produces. Only the
 * scheme matters: a stored "javascript:..." would run for every visitor.
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
            $fail('rules.safe_url_string')->translate();

            return;
        }

        // Browsers strip control characters first, so "java\nscript:..." runs.
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $fail('rules.safe_url_control_characters')->translate();

            return;
        }

        if (! preg_match(self::SCHEME_PATTERN, $value, $matches)) {
            return;
        }

        if (! in_array(strtolower($matches[1]), self::ALLOWED_SCHEMES, true)) {
            $fail('rules.safe_url')->translate();
        }
    }
}
