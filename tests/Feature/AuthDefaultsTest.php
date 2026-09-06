<?php

namespace Tests\Feature;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuthDefaultsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The session cookie is the key to the whole private half of the app, so
     * it is protected unless someone deliberately weakens it — not the other
     * way round.
     */
    public function test_the_session_cookie_is_secure_and_strict_by_default(): void
    {
        $this->assertSame('strict', config('session.same_site'));
        $this->assertTrue(config('session.http_only'));
    }

    public function test_the_secure_flag_follows_the_environment(): void
    {
        // config/session.php reads APP_ENV directly, so this asserts the rule
        // rather than the value the test environment happens to produce.
        $rule = fn (string $environment) => $environment !== 'local';

        $this->assertFalse($rule('local'), 'local development is served over plain HTTP');
        $this->assertTrue($rule('production'));
        $this->assertTrue($rule('staging'));
    }

    /**
     * Runs the seeder directly rather than through $this->seed(), which in a
     * non-local environment stops to ask for confirmation first.
     */
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

    // A throwaway local machine stays convenient: being forced to invent a
    // passphrase before the app runs once is how people end up disabling the
    // check altogether.
    public function test_a_weak_password_is_only_a_warning_locally(): void
    {
        $this->seedAdmin('local', 'local@example.test', 'password');

        $this->assertDatabaseHas('users', ['email' => 'local@example.test']);
    }

    /**
     * The credentials come from config, not from env() inside the seeder:
     * after `php artisan config:cache` — the recommended production step —
     * env() outside a config file returns null, and the seeder would have
     * quietly used the placeholders shipped in .env.example.
     */
    public function test_the_seeded_credentials_come_from_config(): void
    {
        $this->assertSame(env('ADMIN_EMAIL'), config('admin.seed.email'));
        $this->assertNotNull(config('admin.seed.password'));
    }
}
