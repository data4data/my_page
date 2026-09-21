<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The demo login carries a password from the repository, so what matters is
 * not that it works but that it cannot appear anywhere it should not.
 */
class DemoAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Run directly, so what is under test is the seeder rather than db:seed.
     *
     * The environment goes back to `testing` afterwards because Laravel skips
     * CSRF verification only there — leaving it on `local` would 419 the sign-in
     * below and look like a broken password.
     */
    private function runIn(string $environment, ?callable $seeder = null): void
    {
        $this->app->detectEnvironment(fn () => $environment);

        try {
            $seeder ? $seeder() : app(DemoAdminSeeder::class)->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_it_creates_an_admin_that_can_sign_in(): void
    {
        $this->runIn('local');

        $user = User::query()->where('email', DemoAdminSeeder::email())->firstOrFail();

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue(Hash::check(DemoAdminSeeder::password(), $user->password));

        $this->postJson($this->adminUrl('/login'), [
            'email' => DemoAdminSeeder::email(),
            'password' => DemoAdminSeeder::password(),
        ])->assertOk();

        $this->assertAuthenticated();
    }

    // The whole point of the guard: a password in the repository must not
    // become a way into anything that is not somebody's laptop.
    public function test_it_refuses_to_run_outside_local(): void
    {
        $this->runIn('production');

        $this->assertSame(0, User::query()->count());
    }

    // Re-running the seeders must not hand a real account a known password.
    public function test_it_leaves_an_existing_admin_alone(): void
    {
        Role::findOrCreate('admin', 'web');
        $owner = User::factory()->create(['email' => 'owner@example.test', 'password' => Hash::make('a real password')]);
        $owner->assignRole('admin');

        $this->runIn('local');

        $this->assertSame(1, User::query()->count());
        $this->assertNull(User::query()->where('email', DemoAdminSeeder::email())->first());
        $this->assertTrue(Hash::check('a real password', $owner->fresh()->password));
    }

    public function test_running_it_twice_makes_one_account(): void
    {
        $this->runIn('local');
        $this->runIn('local');

        $this->assertSame(1, User::query()->count());
    }

    // The wiring, not just the seeder: `migrate:fresh --seed` is the command
    // this exists to make survivable, and DatabaseSeeder is what it runs.
    public function test_the_default_seeders_leave_a_usable_login_on_a_local_machine(): void
    {
        $this->runIn('local', fn () => $this->seed(DatabaseSeeder::class));

        $this->postJson($this->adminUrl('/login'), [
            'email' => DemoAdminSeeder::email(),
            'password' => DemoAdminSeeder::password(),
        ])->assertOk();

        $this->assertAuthenticated();
    }

    // The reason the credentials live in config: changing them should not mean
    // editing a seeder.
    public function test_it_uses_the_credentials_from_the_configuration(): void
    {
        config([
            'admin.demo.email' => 'someone@my-page.test',
            'admin.demo.password' => 'a-chosen-password',
        ]);

        $this->runIn('local');

        $user = User::query()->where('email', 'someone@my-page.test')->firstOrFail();

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue(Hash::check('a-chosen-password', $user->password));
    }

    /**
     * A key present but left blank in .env is a likelier mistake than a key
     * left out, and either an empty address or an empty password would make an
     * account nobody can sign in to.
     *
     * @return array<int, array{0: string|null}>
     */
    public static function blankValues(): array
    {
        return [[''], ['   '], [null]];
    }

    #[DataProvider('blankValues')]
    public function test_a_blank_setting_falls_back_to_the_default(?string $value): void
    {
        config(['admin.demo.email' => $value, 'admin.demo.password' => $value]);

        $this->runIn('local');

        $user = User::query()->where('email', DemoAdminSeeder::DEFAULT_EMAIL)->firstOrFail();

        $this->assertTrue(Hash::check(DemoAdminSeeder::DEFAULT_PASSWORD, $user->password));
    }
}
