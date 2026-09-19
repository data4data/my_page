<?php

namespace App\Contracts;

use App\Models\User;

/**
 * The second factor's lifecycle, without saying what the factor is.
 *
 * TOTP today (TwoFactorService). Passkeys are the plausible second
 * implementation, and they share every step below — enrol, prove it once,
 * prove it at sign-in, fall back to a recovery code, turn it off — while
 * differing entirely in what "the proof" is.
 *
 * What is deliberately *not* here: `qrCodeSvg()` and `otpauthUri()`. Those
 * are TOTP's own, and a passkey provider has no answer for them. The shape
 * the client needs is `enrolmentDetails()`, which each provider fills with
 * whatever its own enrolment screen has to show.
 */
interface TwoFactorProvider
{
    /**
     * Step one: issue whatever the user has to register, and the recovery
     * codes, without enforcing anything yet. A secret alone is never
     * enforced, so a mis-scanned QR is a retry rather than a lockout.
     */
    public function begin(User $user): User;

    /**
     * What the enrolment screen needs to show. Provider-shaped on purpose:
     * TOTP returns a QR and a typable key, a passkey provider would return a
     * challenge.
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
