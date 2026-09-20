<?php

namespace Tests\Feature;

use App\Models\PortfolioProfile;
use App\Services\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshInstallSeedTest extends TestCase
{
    use RefreshDatabase;

    // A fork should not find somebody else's initials waiting for them.
    public function test_a_fresh_install_seeds_placeholder_initials(): void
    {
        app(PortfolioSeeder::class)->seed();

        $this->assertSame('AB', PortfolioProfile::query()->value('initials'));
    }

    public function test_the_column_default_is_a_placeholder_too(): void
    {
        $default = collect(Schema::getColumns('portfolio_profiles'))
            ->firstWhere('name', 'initials')['default'] ?? null;

        $this->assertStringContainsString('AB', (string) $default);
    }
}
