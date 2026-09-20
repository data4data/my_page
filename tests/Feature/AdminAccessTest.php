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
        $this->get($this->adminUrl())->assertRedirect($this->adminUrl('/login'));
    }

    public function test_guest_cannot_read_admin_portfolio_data(): void
    {
        $this->get($this->adminUrl('/portfolio'))->assertRedirect($this->adminUrl('/login'));
    }

    public function test_the_old_admin_path_no_longer_exists(): void
    {
        $this->get('/admin')->assertNotFound();
    }

    // An unguessable workspace is worth little if its door sits at /login.
    public function test_there_is_no_login_form_at_the_guessable_path(): void
    {
        $this->get('/login')->assertNotFound();
        $this->post('/login', ['email' => 'a@b.test', 'password' => 'x'])->assertNotFound();
        $this->post('/logout')->assertNotFound();
    }

    public function test_the_login_page_lives_behind_the_workspace_prefix(): void
    {
        $this->get($this->adminUrl('/login'))->assertOk();
    }

    // The login page builds its own form action, and asking for this URL
    // already required knowing the prefix.
    public function test_the_login_page_is_told_the_prefix_it_is_already_served_from(): void
    {
        $this->get($this->adminUrl('/login'))
            ->assertOk()
            ->assertSee('<meta name="admin-path" content="test-workspace">', false);
    }

    public function test_the_workspace_prefix_comes_from_config_and_no_other_prefix_answers(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        // phpunit.xml sets a prefix other than the shipped default, so the
        // default answering means a route still hardcodes one.
        $this->assertSame('test-workspace', config('admin.path'));

        $default = 'change-me-before-going-live';

        $this->actingAs($user)->get("/{$default}")->assertNotFound();
        $this->actingAs($user)->get("/{$default}/mijn-agenda")->assertNotFound();
        $this->actingAs($user)->get("/{$default}/tasks")->assertNotFound();
    }

    public function test_the_shell_hands_the_configured_prefix_to_the_frontend(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get($this->adminUrl())
            ->assertOk()
            ->assertSee('<meta name="admin-path" content="test-workspace">', false);
    }

    public function test_the_public_page_does_not_leak_the_workspace_prefix(): void
    {
        // Every route renders the same shell, so the tag must be conditional.
        foreach (['/', '/hi-developer'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('admin-path', false)
                ->assertDontSee(config('admin.path'), false);
        }
    }

    // One bundle for both halves would let a visitor read the private API's
    // endpoint names out of the workspace chunk. bundle-split.test.js guards
    // the other half.
    public function test_the_public_page_is_served_the_public_bundle_only(): void
    {
        foreach (['/', '/hi-developer'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('app-public', false)
                ->assertDontSee('app-admin', false);
        }
    }

    public function test_the_workspace_is_served_the_workspace_bundle(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get($this->adminUrl())
            ->assertOk()
            ->assertSee('app-admin', false)
            ->assertDontSee('app-public', false);
    }

    /** The login page is the door to the workspace, so it needs its bundle. */
    public function test_the_login_page_is_served_the_workspace_bundle(): void
    {
        $this->get($this->adminUrl('/login'))
            ->assertOk()
            ->assertSee('app-admin', false);
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

        foreach ([$this->adminUrl('/mijn-agenda'), $this->adminUrl('/insights'), $this->adminUrl('/edit-content'), $this->adminUrl('/settings')] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
    }

    public function test_guest_is_redirected_to_login_from_each_admin_section_path(): void
    {
        foreach ([$this->adminUrl('/mijn-agenda'), $this->adminUrl('/insights'), $this->adminUrl('/edit-content'), $this->adminUrl('/settings')] as $path) {
            $this->get($path)->assertRedirect($this->adminUrl('/login'));
        }
    }

    public function test_login_with_valid_credentials_authenticates_and_redirects(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['password' => 'secret-password']);
        $user->assignRole('admin');

        $response = $this->postJson($this->adminUrl('/login'), [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertOk()->assertJson(['redirect' => $this->adminUrl()]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_invalid_credentials_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->postJson($this->adminUrl('/login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    // Per address alone misses a spread-out attempt on one account.
    public function test_repeated_failures_against_one_account_are_throttled(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        // A different address each time, so only the per-account limit applies.
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
                ->postJson($this->adminUrl('/login'), ['email' => $user->email, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson($this->adminUrl('/login'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(429);

        // A different account from a fresh address is unaffected.
        $other = User::factory()->create(['password' => 'secret-password']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->postJson($this->adminUrl('/login'), ['email' => $other->email, 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_logout_clears_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post($this->adminUrl('/logout'))->assertRedirect('/');
        $this->assertGuest();
    }
}
