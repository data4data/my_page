<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Enrolment, from inside the workspace. Two-factor is off until the owner
 * turns it on here, which is the point: a fresh install must not depend on
 * having an authenticator to hand.
 */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->state($request));
    }

    /** Step one: issue a secret and show it, without enforcing anything yet. */
    public function store(Request $request): JsonResponse
    {
        $user = $this->twoFactor->begin($request->user());

        return response()->json([
            ...$this->state($request),
            // A data URI rather than raw markup, so the page renders it as an
            // ordinary <img> instead of reaching for v-html. The CSP already
            // allows data: images; it allows no inline anything else.
            'qr_data_uri' => 'data:image/svg+xml;base64,'.base64_encode($this->twoFactor->qrCodeSvg($user)),
            // Shown once, here, because enrolment is the only moment they are
            // useful and the QR is worthless without somewhere to fall back to.
            'setup_key' => $user->two_factor_secret,
            'recovery_codes' => $user->two_factor_recovery_codes,
        ]);
    }

    /** Step two: prove the authenticator works before it is required. */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);

        if (! $this->twoFactor->confirm($request->user(), $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'That code is not valid. Check your authenticator and try again.',
            ]);
        }

        return response()->json($this->state($request));
    }

    /**
     * Turning it off is a change to how you get in, so it asks for the
     * password again. A session left open on an unlocked machine is then not
     * enough to strip the account back to one factor.
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'That password is not correct.',
            ]);
        }

        $this->twoFactor->disable($request->user());

        return response()->json($this->state($request));
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        return response()->json([
            'recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($request->user()),
        ]);
    }

    /** @return array<string, mixed> */
    private function state(Request $request): array
    {
        $user = $request->user()->fresh();

        return [
            // Echoed so the workspace can print the exact console command
            // that turns this off, for the day the phone is gone.
            'email' => $user->email,
            'enabled' => $user->hasTwoFactorEnabled(),
            // A secret with no confirmation means enrolment was started and
            // never finished, which the UI shows as "waiting for a code".
            'pending' => $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled(),
            'recovery_codes_left' => count($user->two_factor_recovery_codes ?? []),
        ];
    }
}
