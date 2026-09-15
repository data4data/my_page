<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Console\Command;

/**
 * The way back in when the authenticator and the recovery codes are both gone.
 * Anything that can lock out the only account needs a way in that does not
 * require signing in.
 */
class DisableTwoFactor extends Command
{
    protected $signature = 'two-factor:disable {email : The account to turn two-factor off for}';

    protected $description = 'Turn off two-factor authentication for an account';

    public function handle(TwoFactorService $twoFactor): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No account found for {$this->argument('email')}.");

            return self::FAILURE;
        }

        if (! $user->two_factor_secret) {
            $this->info("Two-factor is already off for {$user->email}.");

            return self::SUCCESS;
        }

        $twoFactor->disable($user);

        $this->info("Two-factor is now off for {$user->email}. Sign in with the password alone, then set it up again.");

        return self::SUCCESS;
    }
}
