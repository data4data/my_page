<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based one-time passwords, opt-in.
 *
 * The workspace works with a password alone until the owner turns this on, so
 * a fresh install is never blocked on having an authenticator to hand. Once
 * on, a correct password stops being sufficient — which is the only control
 * here that an attacker cannot simply out-wait or out-guess.
 */
class TwoFactorService
{
    // Eight is enough that losing a phone is survivable, few enough that the
    // list stays something you can print on one line each.
    private const RECOVERY_CODE_COUNT = 8;

    // One step either side of now, so a clock a few seconds out still works.
    private const WINDOW = 1;

    public function __construct(private Google2FA $google2fa) {}

    /**
     * Starts enrolment: a secret and a fresh set of recovery codes, neither
     * enforced until confirm() has seen a working code.
     */
    public function begin(User $user): User
    {
        $user->forceFill([
            'two_factor_secret' => $this->google2fa->generateSecretKey(),
            'two_factor_recovery_codes' => $this->newRecoveryCodes(),
            'two_factor_confirmed_at' => null,
        ])->save();

        return $user;
    }

    /**
     * Finishes enrolment. Returns false without enabling anything if the code
     * does not check out, so a mis-scanned QR is a retry rather than a lockout.
     */
    public function confirm(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null || ! $this->verify($user, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => Carbon::now()])->save();

        return true;
    }

    public function disable(User $user): User
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $user;
    }

    public function verify(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        // Digits only: an authenticator never produces anything else, and the
        // library throws on unexpected input rather than returning false.
        $code = preg_replace('/\D/', '', $code);

        if ($code === '' || $code === null) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($user->two_factor_secret, $code, self::WINDOW);
    }

    /**
     * Recovery codes are single use: a matched code is removed before this
     * returns, so the same slip of paper cannot be replayed.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $candidate = trim($code);

        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $candidate)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    /** The otpauth:// URI an authenticator app reads, as a QR code. */
    public function qrCodeSvg(User $user): string
    {
        $renderer = new ImageRenderer(new RendererStyle(228, 0), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($this->otpauthUri($user));
    }

    public function otpauthUri(User $user): string
    {
        // The issuer names this install, so an owner running more than one
        // can tell the entries apart in their authenticator.
        return $this->google2fa->getQRCodeUrl(
            (string) config('app.name'),
            (string) $user->email,
            (string) $user->two_factor_secret,
        );
    }

    /** @return array<int, string> */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }
}
