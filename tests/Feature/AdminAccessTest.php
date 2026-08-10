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
        $this->get('/control-room-ao')->assertRedirect('/login');
    }

    public function test_guest_cannot_read_admin_portfolio_data(): void
    {
        $this->get('/control-room-ao/portfolio')->assertRedirect('/login');
    }

    public function test_the_old_admin_path_no_longer_exists(): void
    {
        $this->get('/admin')->assertNotFound();
    }

    public function test_authenticated_user_without_admin_role_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/control-room-ao')->assertForbidden();
    }

    public function test_admin_role_user_can_access_admin(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get('/control-room-ao')->assertOk();
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

        $response->assertOk()->assertJson(['redirect' => '/control-room-ao']);
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
