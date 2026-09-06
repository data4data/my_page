<?php

namespace Tests\Feature;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityEventTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['password' => 'secret-password']);
        $user->assignRole('admin');

        return $user;
    }

    public function test_a_successful_sign_in_is_recorded_against_the_user(): void
    {
        $admin = $this->admin();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'secret-password'])
            ->assertOk();

        $event = SecurityEvent::sole();
        $this->assertSame(SecurityEventType::LoginSucceeded, $event->type);
        $this->assertSame('203.0.113.7', $event->ip_address);
        $this->assertSame($admin->id, $event->user_id);
    }

    public function test_a_wrong_password_is_recorded_with_the_address_that_was_typed(): void
    {
        $admin = $this->admin();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.4'])
            ->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'wrong'])
            ->assertStatus(422);

        $event = SecurityEvent::sole();
        $this->assertSame(SecurityEventType::LoginFailed, $event->type);
        $this->assertSame('198.51.100.4', $event->ip_address);
        $this->assertSame($admin->email, $event->email);
    }

    /**
     * The case worth seeing most. The limiter answers before the controller
     * runs, so no Failed event fires — without the limiter's own response
     * callback the trail would go quiet exactly when the attack got loud.
     */
    public function test_an_attempt_the_rate_limiter_turns_away_is_still_recorded(): void
    {
        $admin = $this->admin();

        for ($attempt = 0; $attempt < 7; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
                ->postJson($this->adminUrl('/login'), ['email' => $admin->email, 'password' => 'wrong']);
        }

        $this->assertSame(6, SecurityEvent::where('type', SecurityEventType::LoginFailed)->count());
        $this->assertSame(1, SecurityEvent::where('type', SecurityEventType::LoginBlocked)->count());
    }

    public function test_the_trail_is_private_to_the_workspace(): void
    {
        $this->get($this->adminUrl('/security-events'))->assertRedirect($this->adminUrl('/login'));
    }

    public function test_the_rollup_groups_by_address_and_counts_outcomes(): void
    {
        $admin = $this->admin();

        SecurityEvent::create(['type' => SecurityEventType::LoginFailed, 'ip_address' => '203.0.113.1', 'email' => 'a@b.test']);
        SecurityEvent::create(['type' => SecurityEventType::LoginFailed, 'ip_address' => '203.0.113.1', 'email' => 'c@d.test']);
        SecurityEvent::create(['type' => SecurityEventType::LoginBlocked, 'ip_address' => '203.0.113.1']);
        SecurityEvent::create(['type' => SecurityEventType::LoginSucceeded, 'ip_address' => '203.0.113.2', 'user_id' => $admin->id]);

        $body = $this->actingAs($admin)->getJson($this->adminUrl('/security-events'))->assertOk()->json();

        $this->assertSame(12, $body['window_hours']);
        // "failed" is a wrong password and nothing else; a blocked attempt
        // never reached the credential check, so it counts separately.
        $this->assertSame(2, $body['totals']['failed']);
        $this->assertSame(1, $body['totals']['blocked']);
        $this->assertSame(1, $body['totals']['succeeded']);

        // Noisiest address first.
        $this->assertSame('203.0.113.1', $body['by_address'][0]['ip_address']);
        $this->assertSame(3, $body['by_address'][0]['attempts']);
        $this->assertSame(2, $body['by_address'][0]['failed']);
        $this->assertSame(1, $body['by_address'][0]['blocked']);
        // Every attempt lands in exactly one outcome column.
        $row = $body['by_address'][0];
        $this->assertSame($row['attempts'], $row['succeeded'] + $row['failed'] + $row['blocked']);
    }

    public function test_the_rollup_ignores_anything_older_than_the_window(): void
    {
        $admin = $this->admin();

        SecurityEvent::create(['type' => SecurityEventType::LoginFailed, 'ip_address' => '203.0.113.3']);
        SecurityEvent::create(['type' => SecurityEventType::LoginFailed, 'ip_address' => '203.0.113.4'])
            ->forceFill(['created_at' => Carbon::now()->subHours(13)])->save();

        $body = $this->actingAs($admin)->getJson($this->adminUrl('/security-events'))->assertOk()->json();

        $this->assertCount(1, $body['by_address']);
        $this->assertSame('203.0.113.3', $body['by_address'][0]['ip_address']);
        // The raw list is not windowed, so both are still visible there.
        $this->assertCount(2, $body['recent']);
    }

    // IP addresses are personal data and pile up fastest when something is
    // wrong, so rows expire rather than accumulating forever.
    public function test_rows_past_the_retention_window_are_prunable(): void
    {
        SecurityEvent::create(['type' => SecurityEventType::LoginFailed, 'ip_address' => '203.0.113.5']);
        SecurityEvent::create(['type' => SecurityEventType::LoginFailed, 'ip_address' => '203.0.113.6'])
            ->forceFill(['created_at' => Carbon::now()->subDays(SecurityEvent::RETENTION_DAYS + 1)])->save();

        $this->artisan('model:prune', ['--model' => [SecurityEvent::class]])->assertExitCode(0);

        $this->assertSame(['203.0.113.5'], SecurityEvent::pluck('ip_address')->all());
    }
}
