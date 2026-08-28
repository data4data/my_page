<?php

namespace Tests\Feature;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlanningApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_guest_cannot_reach_any_planning_endpoint(): void
    {
        $this->get($this->adminUrl('/tasks?start=2026-01-01&end=2026-01-31'))->assertRedirect('/login');
        $this->get($this->adminUrl('/categories'))->assertRedirect('/login');
    }

    public function test_category_index_returns_global_and_own_categories(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();

        Category::create(['name' => 'Global', 'color' => '#2f75a8']);
        Category::create(['name' => 'Mine', 'color' => '#c5a064', 'user_id' => $admin->id]);
        Category::create(['name' => 'Someone else\'s', 'color' => '#c0503f', 'user_id' => $other->id]);

        $response = $this->actingAs($admin)->getJson($this->adminUrl('/categories'));

        $names = collect($response->json('categories'))->pluck('name');
        $response->assertOk();
        $this->assertTrue($names->contains('Global'));
        $this->assertTrue($names->contains('Mine'));
        $this->assertFalse($names->contains('Someone else\'s'));
    }

    public function test_category_store_scopes_to_the_creating_admin(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson($this->adminUrl('/categories'), [
            'name' => 'Freelance',
            'color' => '#7c9a6b',
        ]);

        $response->assertCreated();
        $this->assertSame($admin->id, $response->json('category.user_id'));
    }

    public function test_category_update_is_forbidden_for_a_category_owned_by_someone_else(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();
        $category = Category::create(['name' => 'Not yours', 'color' => '#c0503f', 'user_id' => $other->id]);

        $this->actingAs($admin)
            ->putJson($this->adminUrl("/categories/{$category->id}"), ['name' => 'Hijacked', 'color' => '#000000'])
            ->assertForbidden();
    }

    public function test_task_index_filters_by_date_range_and_owner(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();

        Task::create(['user_id' => $admin->id, 'title' => 'In range', 'start_datetime' => '2026-06-10 09:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);
        Task::create(['user_id' => $admin->id, 'title' => 'Out of range', 'start_datetime' => '2026-07-01 09:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);
        Task::create(['user_id' => $other->id, 'title' => 'Someone else', 'start_datetime' => '2026-06-11 09:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);

        $response = $this->actingAs($admin)->getJson($this->adminUrl('/tasks?start=2026-06-01&end=2026-06-30'));

        $titles = collect($response->json('tasks'))->pluck('title');
        $response->assertOk();
        $this->assertSame(['In range'], $titles->all());
    }

    public function test_task_store_and_update(): void
    {
        $admin = $this->admin();

        $store = $this->actingAs($admin)->postJson($this->adminUrl('/tasks'), [
            'title' => 'New task',
            'start_datetime' => '2026-06-10 09:00:00',
        ]);
        $store->assertCreated();
        $this->assertSame(TaskStatus::Planned->value, $store->json('task.status'));
        $this->assertSame(TaskSource::Manual->value, $store->json('task.source'));

        $taskId = $store->json('task.id');

        $update = $this->actingAs($admin)->putJson($this->adminUrl("/tasks/{$taskId}"), ['status' => TaskStatus::Done->value]);
        $update->assertOk();
        $this->assertSame(TaskStatus::Done->value, $update->json('task.status'));
    }

    public function test_task_store_rejects_a_payload_missing_the_required_fields(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson($this->adminUrl('/tasks'), ['description' => 'No title, no start.'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'start_datetime']);
    }

    public function test_task_store_rejects_an_end_before_the_start(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson($this->adminUrl('/tasks'), [
                'title' => 'Backwards',
                'start_datetime' => '2026-06-10 12:00:00',
                'end_datetime' => '2026-06-10 09:00:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_datetime']);
    }

    // The update path is deliberately partial: sending only one field must not
    // trip the 'required' rules that apply when creating.
    public function test_task_update_accepts_a_partial_payload(): void
    {
        $admin = $this->admin();
        $task = Task::create([
            'user_id' => $admin->id,
            'title' => 'Original',
            'start_datetime' => '2026-06-10 09:00:00',
            'status' => TaskStatus::Planned,
            'source' => TaskSource::Manual,
        ]);

        $response = $this->actingAs($admin)
            ->putJson($this->adminUrl("/tasks/{$task->id}"), ['result_notes' => 'Went well.']);

        $response->assertOk();
        $this->assertSame('Original', $response->json('task.title'));
        $this->assertSame('Went well.', $response->json('task.result_notes'));
    }

    public function test_a_category_cannot_be_made_its_own_parent(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Work', 'color' => '#2f75a8', 'user_id' => $admin->id]);

        $this->actingAs($admin)
            ->putJson($this->adminUrl("/categories/{$category->id}"), [
                'name' => 'Work',
                'color' => '#2f75a8',
                'parent_id' => $category->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_task_update_is_forbidden_for_a_task_owned_by_someone_else(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();
        $task = Task::create(['user_id' => $other->id, 'title' => 'Not yours', 'start_datetime' => '2026-06-10 09:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);

        $this->actingAs($admin)->putJson($this->adminUrl("/tasks/{$task->id}"), ['title' => 'Hijacked'])->assertForbidden();
        $this->actingAs($admin)->deleteJson($this->adminUrl("/tasks/{$task->id}"))->assertForbidden();
    }

    public function test_timer_start_creates_a_running_log_and_flips_status(): void
    {
        $admin = $this->admin();
        $task = Task::create(['user_id' => $admin->id, 'title' => 'Focus block', 'start_datetime' => '2026-06-10 09:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);

        $response = $this->actingAs($admin)->postJson($this->adminUrl("/tasks/{$task->id}/timer/start"));

        $response->assertOk();
        $this->assertSame(TaskStatus::InProgress->value, $response->json('task.status'));
        $this->assertCount(1, $response->json('task.time_logs'));
        $this->assertNull($response->json('task.time_logs.0.ended_at'));
    }

    public function test_timer_start_does_not_duplicate_an_already_running_log(): void
    {
        $admin = $this->admin();
        $task = Task::create(['user_id' => $admin->id, 'title' => 'Focus block', 'start_datetime' => '2026-06-10 09:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);

        $this->actingAs($admin)->postJson($this->adminUrl("/tasks/{$task->id}/timer/start"));
        $response = $this->actingAs($admin)->postJson($this->adminUrl("/tasks/{$task->id}/timer/start"));

        $this->assertCount(1, $response->json('task.time_logs'));
    }

    public function test_starting_a_timer_stops_and_pauses_any_other_running_task(): void
    {
        $admin = $this->admin();
        $first = Task::create(['user_id' => $admin->id, 'title' => 'First', 'start_datetime' => '2026-06-10 09:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);
        $second = Task::create(['user_id' => $admin->id, 'title' => 'Second', 'start_datetime' => '2026-06-10 11:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);

        $first->timeLogs()->create(['started_at' => Carbon::now()->subMinutes(30)]);

        $response = $this->actingAs($admin)->postJson($this->adminUrl("/tasks/{$second->id}/timer/start"));

        $response->assertOk();

        // Exactly one timer runs across the whole board.
        $this->assertSame(0, $first->fresh()->timeLogs()->whereNull('ended_at')->count());
        $this->assertSame(1, $second->fresh()->timeLogs()->whereNull('ended_at')->count());

        // The handed-over task is parked as paused, not marked done, and its
        // elapsed time is still banked.
        $this->assertSame(TaskStatus::Paused, $first->fresh()->status);
        $this->assertSame(TaskStatus::InProgress, $second->fresh()->status);
        $this->assertEqualsWithDelta(30, $first->timeLogs()->first()->duration_minutes, 1);
    }

    public function test_starting_a_timer_does_not_pause_another_users_running_task(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();

        $theirs = Task::create(['user_id' => $other->id, 'title' => 'Theirs', 'start_datetime' => '2026-06-10 09:00:00', 'status' => TaskStatus::InProgress, 'source' => TaskSource::Manual]);
        $theirs->timeLogs()->create(['started_at' => Carbon::now()->subMinutes(10)]);

        $mine = Task::create(['user_id' => $admin->id, 'title' => 'Mine', 'start_datetime' => '2026-06-10 11:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);

        $this->actingAs($admin)->postJson($this->adminUrl("/tasks/{$mine->id}/timer/start"))->assertOk();

        $this->assertSame(1, $theirs->fresh()->timeLogs()->whereNull('ended_at')->count());
        $this->assertSame(TaskStatus::InProgress, $theirs->fresh()->status);
    }

    public function test_timer_stop_closes_the_running_log_with_a_computed_duration(): void
    {
        $admin = $this->admin();
        $task = Task::create(['user_id' => $admin->id, 'title' => 'Focus block', 'start_datetime' => '2026-06-10 09:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);
        $task->timeLogs()->create(['started_at' => Carbon::now()->subMinutes(25)]);

        $response = $this->actingAs($admin)->postJson($this->adminUrl("/tasks/{$task->id}/timer/stop"));

        $response->assertOk();
        $this->assertNotNull($response->json('task.time_logs.0.ended_at'));
        $this->assertEqualsWithDelta(25, $response->json('task.time_logs.0.duration_minutes'), 1);
    }

    public function test_timer_stop_is_a_safe_no_op_when_nothing_is_running(): void
    {
        $admin = $this->admin();
        $task = Task::create(['user_id' => $admin->id, 'title' => 'Idle task', 'start_datetime' => '2026-06-10 09:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);

        $this->actingAs($admin)->postJson($this->adminUrl("/tasks/{$task->id}/timer/stop"))->assertOk();
    }

    public function test_reflection_upsert_is_idempotent_by_period(): void
    {
        $admin = $this->admin();
        $payload = [
            'period_type' => 'week',
            'period_start' => '2026-06-08',
            'period_end' => '2026-06-14',
            'notes' => 'Good week overall.',
        ];

        $this->actingAs($admin)->putJson($this->adminUrl('/reflections'), $payload)->assertOk();
        $this->actingAs($admin)->putJson($this->adminUrl('/reflections'), [...$payload, 'notes' => 'Updated notes.'])->assertOk();

        $this->assertSame(1, Reflection::count());
        $this->assertSame('Updated notes.', Reflection::first()->notes);
    }

    public function test_reflection_show_returns_the_matching_period(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson($this->adminUrl('/reflections'), [
            'period_type' => 'month',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'notes' => 'Solid month.',
        ]);

        $response = $this->actingAs($admin)->getJson($this->adminUrl('/reflections?').http_build_query([
            'period_type' => 'month',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
        ]));

        $response->assertOk();
        $this->assertSame('Solid month.', $response->json('reflection.notes'));
    }

    public function test_report_totals_minutes_and_groups_by_category(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Learning', 'color' => '#c5a064']);

        $task = Task::create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'title' => 'Study',
            'start_datetime' => '2026-06-10 09:00:00',
            'status' => TaskStatus::Done,
            'source' => TaskSource::Manual,
        ]);
        $task->timeLogs()->create(['started_at' => '2026-06-10 09:00:00', 'ended_at' => '2026-06-10 09:45:00']);

        $response = $this->actingAs($admin)->getJson($this->adminUrl('/reports?').http_build_query([
            'period_type' => 'week',
            'period_start' => '2026-06-08',
        ]));

        $response->assertOk();
        $this->assertSame(45, $response->json('total_minutes'));
        $this->assertSame(1, $response->json('task_count'));
        $this->assertSame('Learning', $response->json('by_category.0.category'));
        $this->assertSame(45, $response->json('by_category.0.minutes'));
    }

    public function test_report_reports_planned_minutes_alongside_tracked_minutes(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Learning', 'color' => '#c5a064']);

        // Explicit planned duration wins.
        $explicit = Task::create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'title' => 'Study',
            'start_datetime' => '2026-06-10 09:00:00',
            'end_datetime' => '2026-06-10 12:00:00',
            'planned_duration_minutes' => 90,
            'status' => TaskStatus::Done,
            'source' => TaskSource::Manual,
        ]);
        $explicit->timeLogs()->create(['started_at' => '2026-06-10 09:00:00', 'ended_at' => '2026-06-10 09:45:00']);

        // No explicit duration: falls back to the scheduled start→end span (30m).
        Task::create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'title' => 'Reading',
            'start_datetime' => '2026-06-11 09:00:00',
            'end_datetime' => '2026-06-11 09:30:00',
            'status' => TaskStatus::Planned,
            'source' => TaskSource::Manual,
        ]);

        // Open-ended task contributes no planned time.
        Task::create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'title' => 'Open ended',
            'start_datetime' => '2026-06-12 09:00:00',
            'status' => TaskStatus::Planned,
            'source' => TaskSource::Manual,
        ]);

        $response = $this->actingAs($admin)->getJson($this->adminUrl('/reports?').http_build_query([
            'period_type' => 'week',
            'period_start' => '2026-06-08',
        ]));

        $response->assertOk();
        $this->assertSame(120, $response->json('total_planned_minutes'));
        $this->assertSame(120, $response->json('by_category.0.planned_minutes'));
        $this->assertSame(45, $response->json('by_category.0.minutes'));
    }
}
