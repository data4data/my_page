<?php

namespace App\Services;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Writes the sign-in trail shown in the workspace's Security tab. Shared,
 * because three different places record attempts: the Login and Failed
 * events, and the rate limiter's response callback.
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
            // A request can send any length; the column is 255.
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255) ?: null,
        ]);
    }
}
