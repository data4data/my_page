<?php

namespace App\Providers;

use App\Enums\SecurityEventType;
use App\Models\User;
use App\Services\SecurityEventRecorder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Empty on purpose. Services in app/Services are concrete classes the
     * container resolves by reflection. Add a binding only when a second
     * implementation exists.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureLoginRateLimiting();
        $this->recordSignInAttempts();
    }

    /**
     * The sign-in trail behind the Security tab. Listeners, not code in
     * AuthController, so an attempt is recorded however it was made.
     */
    private function recordSignInAttempts(): void
    {
        Event::listen(function (Login $event): void {
            app(SecurityEventRecorder::class)->record(
                SecurityEventType::LoginSucceeded,
                $event->user->email ?? null,
                $event->user instanceof User ? $event->user : null,
            );
        });

        Event::listen(function (Failed $event): void {
            app(SecurityEventRecorder::class)->record(
                SecurityEventType::LoginFailed,
                $event->credentials['email'] ?? null,
                $event->user instanceof User ? $event->user : null,
            );
        });
    }

    /**
     * Two limits, because they stop different attacks. Per address alone
     * misses a spread-out attempt on one account; per account alone lets
     * anyone lock the owner out. The account limit is the looser of the two,
     * so ordinary mistyping never trips it.
     */
    private function configureLoginRateLimiting(): void
    {
        // The only hook for a blocked attempt: the limiter answers before the
        // controller runs, so no Failed event fires.
        $blocked = function (Request $request) {
            app(SecurityEventRecorder::class)->record(
                SecurityEventType::LoginBlocked,
                $request->input('email'),
            );

            abort(429, 'Too many sign-in attempts. Please wait a minute and try again.');
        };

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(6)->by('login-address:'.$request->ip())->response($blocked),
            Limit::perMinute(12)->by('login-account:'.Str::lower((string) $request->input('email')))->response($blocked),
        ]);
    }
}
