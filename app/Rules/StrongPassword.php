<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

/**
 * What this app refuses as a workspace password.
 *
 * A rule rather than a check inside the install command, so the same bar
 * applies wherever a password is set next, and so it can be tested without
 * driving a prompt.
 *
 * Deliberately not Password::uncompromised(), which asks haveibeenpwned over
 * the network: installing should not pause, or behave differently, because
 * the machine happens to be offline.
 */
class StrongPassword implements ValidationRule
{
    public const MINIMUM_LENGTH = 12;

    /** What .env.example used to ship, plus the usual suspects. */
    public const REFUSED = [
        'password',
        'secret',
        'admin',
        'changeme',
        'letmein',
        '123456789012',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_string($value) ? $value : '';

        if (mb_strlen($value) < self::MINIMUM_LENGTH) {
            $fail('At least '.self::MINIMUM_LENGTH.' characters, please.');

            return;
        }

        if (in_array(mb_strtolower($value), self::REFUSED, true)) {
            $fail('That is one of the first passwords anyone tries.');
        }
    }

    /**
     * The complaint, or null when the password will do.
     *
     * Runs the rule through the validator rather than calling validate()
     * directly, so a prompt gets the same message a form would.
     */
    public function complaint(string $value): ?string
    {
        $validator = Validator::make(['password' => $value], ['password' => [$this]]);

        return $validator->fails() ? (string) $validator->errors()->first('password') : null;
    }
}
