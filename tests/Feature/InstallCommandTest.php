<?php

namespace Tests\Feature;

use App\Models\PortfolioProfile;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `php artisan app:install` creates this install's identity.
 *
 * It replaced AdminUserSeeder, which read credentials from .env and then
 * spent sixty lines refusing the weak ones it might be handed. A command can
 * ask instead, so a real password never has to sit in a file and a
 * placeholder one can never reach a live install.
 */
class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    private const GOOD = 'correct-horse-battery-staple';

    /** @return array<string, array{0: string}> */
    public static function weakPasswords(): array
    {
        return [
            'too short' => ['short1!'],
            'the placeholder .env.example used to ship' => ['password'],
            'another of the usual suspects' => ['letmein'],
        ];
    }

    #[DataProvider('weakPasswords')]
    public function test_a_weak_password_is_refused(string $password): void
    {
        $this->assertNotNull((new StrongPassword)->complaint($password));
    }

    public function test_a_real_passphrase_is_accepted(): void
    {
        $this->assertNull((new StrongPassword)->complaint(self::GOOD));
    }

    /** The bar applies everywhere, not only outside local. */
    public function test_the_rule_does_not_soften_in_local(): void
    {
        $this->app['env'] = 'local';

        $this->assertNotNull((new StrongPassword)->complaint('password'));
    }

    public function test_it_creates_the_admin_the_role_and_the_profile(): void
    {
        $this->artisan('app:install', [
            '--email' => 'owner@example.test',
            '--initials' => 'ZZ',
        ])
            ->expectsQuestion('Password for that account', self::GOOD)
            ->assertSuccessful();

        $user = User::query()->where('email', 'owner@example.test')->firstOrFail();

        $this->assertTrue($user->hasRole('admin'));
        $this->assertSame('ZZ', PortfolioProfile::query()->value('initials'));
    }

    /**
     * Safe to run twice: it must not duplicate the account, and must not
     * quietly overwrite content someone has already edited.
     */
    public function test_running_it_again_leaves_existing_content_alone(): void
    {
        $this->artisan('app:install', ['--email' => 'owner@example.test', '--initials' => 'ZZ'])
            ->expectsQuestion('Password for that account', self::GOOD)
            ->assertSuccessful();

        PortfolioProfile::query()->firstOrFail()->update(['initials' => 'ED']);

        $this->artisan('app:install', ['--email' => 'owner@example.test'])
            ->expectsConfirmation('An account already exists for owner@example.test. Set a new password for it?', 'no')
            ->assertSuccessful();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, PortfolioProfile::query()->count());
        $this->assertSame('ED', PortfolioProfile::query()->value('initials'));
    }

    /**
     * `migrate` without `--seed` and without `app:install` leaves no profile
     * at all. The editor should fill itself with the placeholder content
     * rather than answer "not found".
     */
    public function test_the_editor_works_on_an_install_that_was_only_migrated(): void
    {
        $this->assertSame(0, PortfolioProfile::query()->count());

        $this->artisan('app:install', ['--email' => 'owner@example.test', '--initials' => 'ZZ'])
            ->expectsQuestion('Password for that account', self::GOOD)
            ->assertSuccessful();

        PortfolioProfile::query()->delete();

        $response = $this->actingAs(User::query()->firstOrFail())
            ->getJson($this->adminUrl('/portfolio'))
            ->assertOk();

        $this->assertNotEmpty($response->json('profile.headline'));
    }

    public function test_it_rejects_an_address_that_is_not_one(): void
    {
        $this->artisan('app:install', ['--email' => 'not-an-address'])->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
