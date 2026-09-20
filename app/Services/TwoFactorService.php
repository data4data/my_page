<?php

namespace App\Services;

use App\Contracts\TwoFactorProvider;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based one-time passwords. Off until the owner turns it on, so a fresh
 * install never needs an authenticator app to sign in.
 */
class TwoFactorService implements TwoFactorProvider
{
    private const RECOVERY_CODE_COUNT = 8;

    // Accept the step either side of now, so a slightly wrong clock works.
    private const WINDOW = 1;

    public function __construct(private Google2FA $google2fa) {}

    /** Starts enrolment. Nothing is enforced until confirm() succeeds. */
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
     * Finishes enrolment. Returns false and enables nothing on a bad code, so
     * a mis-scanned QR is a retry rather than a lockout.
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

        // The library throws on non-digits instead of returning false.
        $code = preg_replace('/\D/', '', $code);

        if ($code === '' || $code === null) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($user->two_factor_secret, $code, self::WINDOW);
    }

    /** Single use: a matched code is deleted before this returns. */
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

    /** The otpauth:// URI, as a QR code. */
    /**
     * TOTP's answer to "what does the enrolment screen show": the QR to scan
     * and the same secret typed out, for an authenticator that cannot use a
     * camera. Both come off the user, so this reads rather than writes.
     *
     * @return array<string, mixed>
     */
    public function enrolmentDetails(User $user): array
    {
        return [
            'qr_data_uri' => 'data:image/svg+xml;base64,'.base64_encode($this->qrCodeSvg($user)),
            'setup_key' => $user->two_factor_secret,
        ];
    }

    public function qrCodeSvg(User $user): string
    {
        $renderer = new ImageRenderer(new RendererStyle(228, 0), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($this->otpauthUri($user));
    }

    public function otpauthUri(User $user): string
    {
        // The issuer names the install, so more than one is tellable apart.
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
