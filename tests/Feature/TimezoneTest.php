<?php

namespace Tests\Feature;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The owner's zone, and the one thing it changes: which period a tracked
 * minute belongs to. Task times are wall-clock and time logs are real
 * instants, so the report cannot compare both to the same raw boundary.
 */
class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $timezone = 'UTC'): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['timezone' => $timezone]);
        $user->assignRole('admin');

        return $user;
    }

    private function report(User $user, string $periodStart): TestResponse
    {
        return $this->actingAs($user)->getJson($this->adminUrl('/reports?').http_build_query([
            'period_type' => 'week',
            'period_start' => $periodStart,
        ]));
    }

    public function test_an_install_that_never_set_a_zone_is_in_utc(): void
    {
        $this->assertSame('UTC', $this->admin()->timezone);
    }

    public function test_guest_cannot_read_or_change_the_timezone(): void
    {
        $this->get($this->adminUrl('/timezone'))->assertRedirect($this->adminUrl('/login'));
        $this->putJson($this->adminUrl('/timezone'), ['timezone' => 'Europe/Amsterdam'])->assertUnauthorized();
    }

    public function test_the_picker_is_offered_only_values_the_save_accepts(): void
    {
        $response = $this->actingAs($this->admin())->getJson($this->adminUrl('/timezone'));

        $response->assertOk();
        $this->assertSame('UTC', $response->json('timezone'));
        $this->assertContains('Europe/Amsterdam', $response->json('options'));
    }

    public function test_the_owner_can_change_their_zone(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/timezone'), ['timezone' => 'Europe/Amsterdam'])
            ->assertOk();

        $this->assertSame('Europe/Amsterdam', $admin->fresh()->timezone);
    }

    public function test_a_zone_that_does_not_exist_is_refused(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/timezone'), ['timezone' => 'Mars/Olympus_Mons'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['timezone']);

        $this->assertSame('UTC', $admin->fresh()->timezone);
    }

    /**
     * 00:30 on Monday in Amsterdam is 22:30 on Sunday in UTC, which is what
     * started_at holds. Counted raw, those minutes fall in the week before the
     * one the owner spent them in.
     */
    public function test_minutes_tracked_just_after_local_midnight_belong_to_that_week(): void
    {
        $admin = $this->admin('Europe/Amsterdam');

        $task = Task::create([
            'user_id' => $admin->id,
            'title' => 'Late start',
            // Wall-clock, exactly as the owner typed it.
            'start_datetime' => '2026-06-08 00:30:00',
            'status' => TaskStatus::InProgress,
            'source' => TaskSource::Manual,
        ]);

        // The same half hour as an instant: 22:30 UTC on the Sunday before.
        $task->timeLogs()->create([
            'started_at' => '2026-06-07 22:30:00',
            'ended_at' => '2026-06-07 23:00:00',
        ]);

        $this->assertSame(30, $this->report($admin, '2026-06-08')->assertOk()->json('total_minutes'));
        $this->assertSame(0, $this->report($admin, '2026-06-01')->assertOk()->json('total_minutes'));
    }

    /**
     * The far edge of the same window: a UTC boundary runs six hours into the
     * owner's next week, so minutes spent on Monday morning were reported as
     * part of the week that had already ended.
     */
    public function test_minutes_tracked_after_the_local_week_ends_belong_to_the_next_one(): void
    {
        $admin = $this->admin('Europe/Amsterdam');

        $task = Task::create([
            'user_id' => $admin->id,
            'title' => 'Next week already',
            'start_datetime' => '2026-06-15 00:30:00',
            'status' => TaskStatus::InProgress,
            'source' => TaskSource::Manual,
        ]);

        // 00:30 on Monday 15 June in Amsterdam is 22:30 UTC on the Sunday.
        $task->timeLogs()->create([
            'started_at' => '2026-06-14 22:30:00',
            'ended_at' => '2026-06-14 22:50:00',
        ]);

        $this->assertSame(0, $this->report($admin, '2026-06-08')->assertOk()->json('total_minutes'));
        $this->assertSame(20, $this->report($admin, '2026-06-15')->assertOk()->json('total_minutes'));
    }

    /** West of UTC shifts the other way, so the same fix has to work in both. */
    public function test_a_zone_behind_utc_shifts_the_window_the_other_way(): void
    {
        $admin = $this->admin('America/New_York');

        $task = Task::create([
            'user_id' => $admin->id,
            'title' => 'Evening work',
            'start_datetime' => '2026-06-14 20:00:00',
            'status' => TaskStatus::InProgress,
            'source' => TaskSource::Manual,
        ]);

        // 20:00 on Sunday in New York is 00:00 on Monday in UTC.
        $task->timeLogs()->create([
            'started_at' => '2026-06-15 00:00:00',
            'ended_at' => '2026-06-15 00:40:00',
        ]);

        $this->assertSame(40, $this->report($admin, '2026-06-08')->assertOk()->json('total_minutes'));
        $this->assertSame(0, $this->report($admin, '2026-06-15')->assertOk()->json('total_minutes'));
    }

    /** Planned minutes are wall-clock on both sides, so the zone leaves them alone. */
    public function test_planned_minutes_are_unaffected_by_the_zone(): void
    {
        $admin = $this->admin('Europe/Amsterdam');

        Task::create([
            'user_id' => $admin->id,
            'title' => 'Planned',
            'start_datetime' => '2026-06-08 00:30:00',
            'end_datetime' => '2026-06-08 01:30:00',
            'status' => TaskStatus::Planned,
            'source' => TaskSource::Manual,
        ]);

        $this->assertSame(60, $this->report($admin, '2026-06-08')->assertOk()->json('total_planned_minutes'));
    }
}
