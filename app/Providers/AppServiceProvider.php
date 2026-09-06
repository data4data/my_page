<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Deliberately empty. Services in app/Services are plain concrete classes
     * that the container resolves by reflection, so binding them here would be
     * ceremony — add one only when a second implementation actually exists.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureLoginRateLimiting();
    }

    /**
     * Two limits, because they stop different attacks.
     *
     * Keying on the address alone — which is all a bare `throttle:6,1` does —
     * stops one machine walking a password list, but not a spread-out attempt
     * against a single account. Keying on the account alone lets anyone lock
     * the owner out. Applying both costs an attacker either way, and the
     * account limit is the looser of the two so ordinary mistyping never
     * trips it.
     */
    private function configureLoginRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(6)->by('login-address:'.$request->ip()),
            Limit::perMinute(12)->by('login-account:'.Str::lower((string) $request->input('email'))),
        ]);
    }
}
