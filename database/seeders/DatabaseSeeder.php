<?php

namespace Database\Seeders;

use App\Support\DefaultPortfolioContent;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DefaultPortfolioContent::seed();
        $this->call(AdminUserSeeder::class);
        $this->call(CategorySeeder::class);

        // Demo tasks are local-only: never let a `git pull` + `migrate --seed`
        // on the live instance overwrite real planning data with sample rows.
        if (app()->environment('local')) {
            $this->call(DemoWeekSeeder::class);
        }
    }
}
