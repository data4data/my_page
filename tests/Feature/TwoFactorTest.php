<?php

namespace Tests\Feature;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['password' => 'secret-password']);
        $user->assignRole('admin');

        return $user;
    }

    private function currentCode(User $user): string
    {
        return app(Google2FA::class)->getCurrentOtp($user->fresh()->two_factor_secret);
    }

    /** Enrols and confirms, the way the workspace UI does. */
    private function enrol(User $user): User
    {
        app(TwoFactorService::class)->begin($user);
        $this->assertTrue(app(TwoFactorService::class)->confirm($user, $this->currentCode($user)));

        return $user->fresh();
    }

    public function test_the_workspace_works_with_a_password_alone_until_it_is_turned_on(): void
    {
        $admin = $this->admin();

        $this->assertFalse($admin->hasTwoFactorEnabled());

        $this->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('redirect', $this->adminUrl())
            ->assertJsonMissingPath('two_factor');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_secret_is_not_enforced_until_a_code_has_been_checked(): void
    {
        $admin = $this->admin();
        app(TwoFactorService::class)->begin($admin);

        // Setup started but never finished: a mis-scanned QR must not lock
        // the owner out of their own workspace.
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());

        $this->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonMissingPath('two_factor');
    }

    public function test_a_correct_password_alone_is_no_longer_enough_once_enabled(): void
    {
        $admin = $this->enrol($this->admin());

        $this->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonMissingPath('redirect');

        // The whole point: the password was right and nobody is signed in.
        $this->assertGuest();
    }

    public function test_the_code_completes_the_sign_in(): void
    {
        $admin = $this->enrol($this->admin());

        $this->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'secret-password'])->assertOk();
        $this->assertGuest();

        $this->postJson($this->adminUrl('/two-factor-challenge'), ['code' => $this->currentCode($admin)])
            ->assertOk()
            ->assertJsonPath('redirect', $this->adminUrl());

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_wrong_code_does_not_sign_anyone_in_and_is_recorded(): void
    {
        $admin = $this->enrol($this->admin());

        $this->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'secret-password'])->assertOk();

        $this->postJson($this->adminUrl('/two-factor-challenge'), ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertGuest();
        $this->assertSame(1, SecurityEvent::where('type', SecurityEventType::TwoFactorFailed)->count());
    }

    public function test_the_trail_does_not_claim_a_sign_in_that_stopped_at_the_second_factor(): void
    {
        $admin = $this->enrol($this->admin());

        $this->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'secret-password'])->assertOk();

        // Password right, nobody in: recording this as "signed in" would make
        // the Security tab lie about the one case it exists to show.
        $this->assertSame(0, SecurityEvent::where('type', SecurityEventType::LoginSucceeded)->count());
    }

    public function test_a_wrong_password_is_still_recorded_as_a_failure(): void
    {
        $admin = $this->enrol($this->admin());

        $this->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'wrong'])
            ->assertStatus(422);

        $this->assertSame(1, SecurityEvent::where('type', SecurityEventType::LoginFailed)->count());
    }

    public function test_a_recovery_code_works_once_and_only_once(): void
    {
        $admin = $this->enrol($this->admin());
        $code = $admin->two_factor_recovery_codes[0];

        $this->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'secret-password'])->assertOk();
        $this->postJson($this->adminUrl('/two-factor-challenge'), ['recovery_code' => $code])->assertOk();
        $this->assertAuthenticatedAs($admin);

        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);
        $this->assertSame(1, SecurityEvent::where('type', SecurityEventType::TwoFactorRecoveryUsed)->count());

        // The same slip of paper cannot be replayed.
        $this->post($this->adminUrl('/logout'));
        $this->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'secret-password'])->assertOk();
        $this->postJson($this->adminUrl('/two-factor-challenge'), ['recovery_code' => $code])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_the_challenge_cannot_be_taken_without_passing_the_password_step(): void
    {
        $this->enrol($this->admin());

        $this->postJson($this->adminUrl('/two-factor-challenge'), ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertGuest();
    }

    public function test_turning_it_off_requires_the_password_again(): void
    {
        $admin = $this->enrol($this->admin());

        $this->actingAs($admin)
            ->deleteJson($this->adminUrl('/two-factor'), ['password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());

        $this->actingAs($admin)
            ->deleteJson($this->adminUrl('/two-factor'), ['password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_enrolment_hands_back_a_qr_a_setup_key_and_recovery_codes(): void
    {
        $admin = $this->admin();

        $body = $this->actingAs($admin)->postJson($this->adminUrl('/two-factor'))->assertOk()->json();

        // A data URI, so the page can use a plain <img> and never v-html.
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $body['qr_data_uri']);
        $this->assertStringContainsString('<svg', base64_decode(substr($body['qr_data_uri'], 26)));
        $this->assertNotEmpty($body['setup_key']);
        $this->assertCount(8, $body['recovery_codes']);
        // Issued but not yet enforced.
        $this->assertFalse($body['enabled']);
        $this->assertTrue($body['pending']);
    }

    public function test_the_secret_never_leaves_in_a_serialized_user(): void
    {
        $admin = $this->enrol($this->admin());

        $json = $admin->toJson();

        $this->assertStringNotContainsString('two_factor_secret', $json);
        $this->assertStringNotContainsString($admin->two_factor_secret, $json);
    }

    // Anything that can lock the only account out of the only workspace needs
    // a way back in that does not itself require signing in.
    public function test_the_console_command_is_a_way_back_in(): void
    {
        $admin = $this->enrol($this->admin());

        $this->artisan('two-factor:disable', ['email' => $admin->email])->assertExitCode(0);

        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());

        $this->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('redirect', $this->adminUrl());
    }
}
