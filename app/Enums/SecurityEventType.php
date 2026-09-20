<?php

namespace App\Enums;

enum SecurityEventType: string
{
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    // Turned away by the rate limiter: no credential check happened at all.
    case LoginBlocked = 'login_blocked';
    // Password was right, second factor was not.
    case TwoFactorFailed = 'two_factor_failed';
    case TwoFactorRecoveryUsed = 'two_factor_recovery_used';

    public function isFailure(): bool
    {
        return $this !== self::LoginSucceeded;
    }
}
