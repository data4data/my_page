<?php

namespace App\Providers;

use App\Contracts\PortfolioSeedContent;
use App\Contracts\TwoFactorProvider;
use App\Enums\SecurityEventType;
use App\Services\SecurityEventRecorder;
use App\Services\TwoFactorService;
use App\Support\DefaultPortfolioContent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The two contracts that have somewhere to go.
     *
     * Everything else in app/Services is a concrete class the container
     * resolves by reflection, and stays that way: an interface with one
     * implementation behind it is a file and an indirection. These two are
     * different — a second factor that is not TOTP is a real prospect, and a
     * fork replacing the seeded page is the whole point of the project being
     * forkable.
     */
    public function register(): void
    {
        $this->app->bind(TwoFactorProvider::class, TwoFactorService::class);
        $this->app->bind(PortfolioSeedContent::class, DefaultPortfolioContent::class);
    }

    /**
     * Only the rate limiter. The sign-in trail's listeners live in
     * app/Listeners and are discovered by Laravel from their handle()
     * signature — nothing registers them.
     */
    public function boot(): void
    {
        $this->configureLoginRateLimiting();
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
