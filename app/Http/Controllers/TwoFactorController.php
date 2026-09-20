<?php

namespace App\Http\Controllers;

use App\Contracts\TwoFactorProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Enrolment, from inside the workspace. Off until the owner turns it on here. */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorProvider $twoFactor) {}

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
            // A data URI, so the page renders an ordinary <img> rather than
            // reaching for v-html, which the CSP would block.
            ...$this->twoFactor->enrolmentDetails($user),
            // Shown once: the QR is worthless with nothing to fall back to.
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
     * Asks for the password again, so a session left open on an unlocked
     * machine cannot strip the account back to one factor.
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
            // Echoed so the workspace can print the console command that
            // turns this off, for the day the phone is gone.
            'email' => $user->email,
            'enabled' => $user->hasTwoFactorEnabled(),
            // A secret with no confirmation: enrolment started, never finished.
            'pending' => $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled(),
            'recovery_codes_left' => count($user->two_factor_recovery_codes ?? []),
        ];
    }
}
