<?php

namespace Database\Seeders;

use App\Services\PortfolioSeeder;
use Illuminate\Database\Seeder;

/**
 * Samples only. A real install's identity — the admin account and the profile
 * — is created by `php artisan app:install`; the demo login below exists so a
 * `migrate:fresh --seed` on a development machine is not locked out.
 */
class DatabaseSeeder extends Seeder
{
    public function run(PortfolioSeeder $seeder): void
    {
        $seeder->seed();
        $this->call(CategorySeeder::class);

        // Local only: a `migrate --seed` on a live instance must never bury
        // real planning data under sample rows, and must never leave behind a
        // login whose password is in the repository.
        if (app()->environment('local')) {
            // First: the sample week needs an owner to hang off.
            $this->call(DemoAdminSeeder::class);
            $this->call(DemoWeekSeeder::class);
        }
    }
}
