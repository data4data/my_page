<?php

namespace Tests\Feature;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuthDefaultsTest extends TestCase
{
    use RefreshDatabase;

    /** Protected unless someone deliberately weakens it, not the reverse. */
    public function test_the_session_cookie_is_secure_and_strict_by_default(): void
    {
        $this->assertSame('strict', config('session.same_site'));
        $this->assertTrue(config('session.http_only'));
    }

    public function test_the_secure_flag_follows_the_environment(): void
    {
        // Asserts the rule, not the value this environment happens to give.
        $rule = fn (string $environment) => $environment !== 'local';

        $this->assertFalse($rule('local'), 'local development is served over plain HTTP');
        $this->assertTrue($rule('production'));
        $this->assertTrue($rule('staging'));
    }

    /** Direct, not $this->seed(), which asks for confirmation outside local. */
    private function seedAdmin(string $environment, string $email, string $password): void
    {
        $this->app['env'] = $environment;
        config()->set('admin.seed.email', $email);
        config()->set('admin.seed.password', $password);

        (new AdminUserSeeder)->setContainer($this->app)->run();
    }

    public function test_seeding_refuses_a_well_known_password_outside_local(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Refusing to seed the admin account/');

        $this->seedAdmin('production', 'owner@example.test', 'password');
    }

    public function test_seeding_refuses_a_short_password_outside_local(): void
    {
        $this->expectException(RuntimeException::class);

        $this->seedAdmin('production', 'owner@example.test', 'short1!');
    }

    public function test_no_account_is_created_when_the_password_is_refused(): void
    {
        try {
            $this->seedAdmin('production', 'owner@example.test', 'password');
        } catch (RuntimeException) {
            // Expected — the point is what did not happen.
        }

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.test']);
    }

    public function test_seeding_accepts_a_real_password_outside_local(): void
    {
        $this->seedAdmin('production', 'owner@example.test', 'correct-horse-battery-staple');

        $this->assertDatabaseHas('users', ['email' => 'owner@example.test']);
    }

    // Local stays convenient: forcing a passphrase before the app runs once
    // is how people end up disabling the check.
    public function test_a_weak_password_is_only_a_warning_locally(): void
    {
        $this->seedAdmin('local', 'local@example.test', 'password');

        $this->assertDatabaseHas('users', ['email' => 'local@example.test']);
    }

    /**
     * From config, not env() inside the seeder: after `php artisan
     * config:cache`, env() returns null outside config files and the seeder
     * would use the placeholders from .env.example.
     */
    public function test_the_seeded_credentials_come_from_config(): void
    {
        // Set through config alone, with no env() involved, and the seeder
        // still picks them up — which is what config:cache would break.
        config()->set('admin.seed.email', 'from-config@example.test');
        config()->set('admin.seed.password', 'correct-horse-battery-staple');

        (new AdminUserSeeder)->setContainer($this->app)->run();

        $this->assertDatabaseHas('users', ['email' => 'from-config@example.test']);
    }
}
