<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

/**
 * What this app refuses as a workspace password. Not Password::uncompromised(),
 * which asks haveibeenpwned over the network — installing must work offline.
 */
class StrongPassword implements ValidationRule
{
    public const MINIMUM_LENGTH = 12;

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

    /** The complaint, or null when the password will do. */
    public function complaint(string $value): ?string
    {
        $validator = Validator::make(['password' => $value], ['password' => [$this]]);

        return $validator->fails() ? (string) $validator->errors()->first('password') : null;
    }
}
