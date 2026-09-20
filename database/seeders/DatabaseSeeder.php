<?php

namespace Database\Seeders;

use App\Services\PortfolioSeeder;
use Illuminate\Database\Seeder;

/**
 * Samples only. This install's identity — the admin account and the profile —
 * is created by `php artisan app:install`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(PortfolioSeeder $seeder): void
    {
        $seeder->seed();
        $this->call(CategorySeeder::class);

        // Local only: a `migrate --seed` on a live instance must never bury
        // real planning data under sample rows.
        if (app()->environment('local')) {
            $this->call(DemoWeekSeeder::class);
        }
    }
}
