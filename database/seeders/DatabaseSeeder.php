<?php

namespace Database\Seeders;

use App\Support\DefaultPortfolioContent;
use Illuminate\Database\Seeder;

/**
 * Samples only.
 *
 * This install's *identity* — the admin account and the profile — is created
 * by `php artisan app:install`, which can ask for a password instead of
 * reading one out of a file. What is left here is placeholder page content,
 * the shared categories, and a demo week: things a fresh clone wants so the
 * app has something to show, and that a live instance must never be handed.
 */
class DatabaseSeeder extends Seeder
{
    public function run(DefaultPortfolioContent $defaults): void
    {
        $defaults->seed();
        $this->call(CategorySeeder::class);

        // Demo tasks are local-only: never let a `git pull` + `migrate --seed`
        // on the live instance overwrite real planning data with sample rows.
        if (app()->environment('local')) {
            $this->call(DemoWeekSeeder::class);
        }
    }
}
