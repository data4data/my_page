<?php

namespace App\Services;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Writes the sign-in trail the workspace's Security tab reads back.
 *
 * A service rather than logic inside the listeners, because the three things
 * that feed it arrive by three different routes — two auth events and the
 * rate limiter's own response callback — and all three should record the
 * same shape.
 */
class SecurityEventRecorder
{
    public function __construct(private Request $request) {}

    public function record(SecurityEventType $type, ?string $email = null, ?User $user = null): SecurityEvent
    {
        return SecurityEvent::create([
            'type' => $type,
            'ip_address' => $this->request->ip(),
            'email' => $email ? mb_substr($email, 0, 255) : null,
            'user_id' => $user?->id,
            // Truncated to the column, since a request can send any length
            // it likes and this is only ever read by eye.
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255) ?: null,
        ]);
    }
}
