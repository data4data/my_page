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
    }
}
