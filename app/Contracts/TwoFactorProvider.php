<?php

namespace App\Contracts;

use App\Models\User;

/**
 * The second factor's lifecycle, without saying what the factor is. TOTP's own
 * QR and otpauth URI are not here — a passkey provider has no answer for them,
 * so enrolment screens ask enrolmentDetails() instead.
 */
interface TwoFactorProvider
{
    /**
     * Step one: issue the secret and recovery codes, enforcing nothing yet, so
     * a mis-scanned QR is a retry rather than a lockout.
     */
    public function begin(User $user): User;

    /**
     * What the enrolment screen shows — provider-shaped: a QR for TOTP, a
     * challenge for a passkey.
     *
     * @return array<string, mixed>
     */
    public function enrolmentDetails(User $user): array;

    /** Step two: a real proof, which is what actually turns it on. */
    public function confirm(User $user, string $code): bool;

    /** At sign-in. */
    public function verify(User $user, string $code): bool;

    /** Single-use, and the way back in when the device is gone. */
    public function consumeRecoveryCode(User $user, string $code): bool;

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array;

    public function disable(User $user): User;
}
