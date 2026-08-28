<?php

namespace Database\Seeders;

use App\Support\DefaultPortfolioContent;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Laravel resolves seeder run() arguments through the container, so the
    // now-instance-based content class arrives here the same way it does in
    // PortfolioContentService.
    public function run(DefaultPortfolioContent $defaults): void
    {
        $defaults->seed();
        $this->call(AdminUserSeeder::class);
        $this->call(CategorySeeder::class);

        // Demo tasks are local-only: never let a `git pull` + `migrate --seed`
        // on the live instance overwrite real planning data with sample rows.
        if (app()->environment('local')) {
            $this->call(DemoWeekSeeder::class);
        }
    }
}
