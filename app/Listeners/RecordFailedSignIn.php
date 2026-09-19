<?php

namespace App\Listeners;

use App\Enums\SecurityEventType;
use App\Models\User;
use App\Services\SecurityEventRecorder;
use Illuminate\Auth\Events\Failed;

/**
 * The same for an attempt that did not work.
 *
 * The email is read off the credentials rather than the user, because on a
 * failure there may be no user — someone typing an address that does not
 * exist is exactly what the trail is for.
 */
class RecordFailedSignIn
{
    public function __construct(private SecurityEventRecorder $recorder) {}

    public function handle(Failed $event): void
    {
        $this->recorder->record(
            SecurityEventType::LoginFailed,
            $event->credentials['email'] ?? null,
            $event->user instanceof User ? $event->user : null,
        );
    }
}
