<?php

namespace App\Enums;

// Plain string column validated against this, not a DB enum — same
// MySQL/SQLite portability reason as TaskStatus.
enum SecurityEventType: string
{
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    // The rate limiter turned the request away before it reached the
    // controller, so no credential check happened at all.
    case LoginBlocked = 'login_blocked';

    public function isFailure(): bool
    {
        return $this !== self::LoginSucceeded;
    }
}
