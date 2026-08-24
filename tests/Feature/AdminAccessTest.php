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

    public function test_logout_clears_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
