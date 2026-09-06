<?php

namespace App\Http\Controllers;

use App\Enums\SecurityEventType;
use App\Models\User;
use App\Services\SecurityEventRecorder;
use App\Services\TwoFactorService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Where the pending user waits between a correct password and a correct
    // second factor. Nothing is authenticated while this is set.
    private const PENDING_KEY = 'two_factor.pending_id';

    private const PENDING_REMEMBER_KEY = 'two_factor.remember';

    public function __construct(
        private TwoFactorService $twoFactor,
        private SecurityEventRecorder $recorder,
    ) {}

    /**
     * Called via fetch from resources/js/pages/admin/LoginPage.vue.
     *
     * Auth::validate() rather than Auth::attempt(): it checks the password
     * without starting a session, so an account with two-factor on is not
     * briefly signed in and then signed out again — and the Login event, which
     * the security trail records as "signed in", only fires once the person
     * has actually got all the way in.
     */
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::validate($credentials)) {
            // validate() fires Validated on success but nothing on failure,
            // so the trail is told here rather than being left blind.
            event(new Failed('web', Auth::getLastAttempted(), $credentials));

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::getLastAttempted();

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::PENDING_KEY, $user->getAuthIdentifier());
            $request->session()->put(self::PENDING_REMEMBER_KEY, $request->boolean('remember'));

            return response()->json(['two_factor' => true]);
        }

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    /**
     * Second step for an account with two-factor on. Accepts either a code
     * from the authenticator or one of the single-use recovery codes.
     */
    public function challenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:12'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user) {
            // The password step has expired or was never taken, so there is
            // nothing to challenge. Start again rather than hinting at state.
            throw ValidationException::withMessages([
                'code' => 'Your sign-in has expired. Please enter your password again.',
            ]);
        }

        if (filled($data['recovery_code'] ?? null)) {
            if (! $this->twoFactor->consumeRecoveryCode($user, $data['recovery_code'])) {
                return $this->rejectChallenge($user, 'That recovery code is not valid.');
            }

            $this->recorder->record(SecurityEventType::TwoFactorRecoveryUsed, $user->email, $user);
        } elseif (! $this->twoFactor->verify($user, (string) ($data['code'] ?? ''))) {
            return $this->rejectChallenge($user, 'That code is not valid. Check your authenticator and try again.');
        }

        $remember = (bool) $request->session()->pull(self::PENDING_REMEMBER_KEY, false);
        $request->session()->forget(self::PENDING_KEY);

        return $this->completeLogin($request, $user, $remember);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function completeLogin(Request $request, User $user, bool $remember): JsonResponse
    {
        Auth::login($user, $remember);

        $request->session()->regenerate();

        // Fall back to the private workspace's configured prefix
        // (config/admin.php) rather than a literal path, so a renamed
        // ADMIN_PATH still lands correctly.
        return response()->json([
            'redirect' => $request->session()->pull('url.intended', '/'.config('admin.path')),
        ]);
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::PENDING_KEY);

        return $id ? User::find($id) : null;
    }

    private function rejectChallenge(User $user, string $message): never
    {
        $this->recorder->record(SecurityEventType::TwoFactorFailed, $user->email, $user);

        throw ValidationException::withMessages(['code' => $message]);
    }
}
