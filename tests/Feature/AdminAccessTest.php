<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_visiting_admin(): void
    {
        $this->get($this->adminUrl())->assertRedirect('/login');
    }

    public function test_guest_cannot_read_admin_portfolio_data(): void
    {
        $this->get($this->adminUrl('/portfolio'))->assertRedirect('/login');
    }

    public function test_the_old_admin_path_no_longer_exists(): void
    {
        $this->get('/admin')->assertNotFound();
    }

    public function test_the_workspace_prefix_comes_from_config_and_no_other_prefix_answers(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        // phpunit.xml sets ADMIN_PATH to something other than the default that
        // ships in config/admin.php, so that default must NOT resolve here.
        // If it does, some route is still hardcoding a prefix.
        $this->assertSame('test-workspace', config('admin.path'));

        $this->actingAs($user)->get('/control-room')->assertNotFound();
        $this->actingAs($user)->get('/control-room/mijn-agenda')->assertNotFound();
        $this->actingAs($user)->get('/control-room/tasks')->assertNotFound();
    }

    public function test_the_shell_hands_the_configured_prefix_to_the_frontend(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        // resources/js/shared/admin-path.js reads this tag to build both the
        // vue-router paths and every admin fetch URL.
        $this->actingAs($user)
            ->get($this->adminUrl())
            ->assertOk()
            ->assertSee('<meta name="admin-path" content="test-workspace">', false);
    }

    public function test_the_public_page_does_not_leak_the_workspace_prefix(): void
    {
        // Every route renders the same Blade shell, so the tag that hands the
        // prefix to the frontend used to go out on the public visit card too —
        // putting the deliberately-unguessable private URL in page source for
        // any anonymous visitor. Guests are told nothing.
        foreach (['/', '/hi-developer', '/login'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('admin-path', false)
                ->assertDontSee(config('admin.path'), false);
        }
    }

    public function test_a_signed_in_non_admin_is_not_told_the_workspace_prefix(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertDontSee(config('admin.path'), false);
    }

    public function test_authenticated_user_without_admin_role_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get($this->adminUrl())->assertForbidden();
    }

    public function test_admin_role_user_can_access_admin(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get($this->adminUrl())->assertOk();
    }

    public function test_admin_role_user_can_access_each_admin_section_path(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        foreach ([$this->adminUrl('/mijn-agenda'), $this->adminUrl('/insights'), $this->adminUrl('/edit-content')] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
    }

    public function test_guest_is_redirected_to_login_from_each_admin_section_path(): void
    {
        foreach ([$this->adminUrl('/mijn-agenda'), $this->adminUrl('/insights'), $this->adminUrl('/edit-content')] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_login_with_valid_credentials_authenticates_and_redirects(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['password' => 'secret-password']);
        $user->assignRole('admin');

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertOk()->assertJson(['redirect' => $this->adminUrl()]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_invalid_credentials_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    // A bare per-address throttle stops one machine walking a password list
    // but not a spread-out attempt against one account, so the limiter keys
    // on both. See AppServiceProvider::configureLoginRateLimiting().
    public function test_repeated_failures_against_one_account_are_throttled(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        // Each attempt comes from a different address, so only the
        // per-account limit can be what stops them.
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
                ->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(429);

        // A different account from a fresh address is unaffected.
        $other = User::factory()->create(['password' => 'secret-password']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->postJson('/login', ['email' => $other->email, 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_logout_clears_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
