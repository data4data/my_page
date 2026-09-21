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

    private function task(User $user, string $start, ?string $end = null): Task
    {
        return Task::create([
            'user_id' => $user->id,
            'title' => 'Task',
            'start_datetime' => $start,
            'end_datetime' => $end,
            'status' => TaskStatus::Planned,
            'source' => TaskSource::Manual,
        ]);
    }

    public function test_guest_cannot_reach_any_planning_endpoint(): void
    {
        $this->get($this->adminUrl('/tasks?start=2026-01-01&end=2026-01-31'))->assertRedirect($this->adminUrl('/login'));
        $this->get($this->adminUrl('/categories'))->assertRedirect($this->adminUrl('/login'));
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

    // A partial update must not trip the create path's 'required' rules.
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

    // after_or_equal does nothing when start_datetime is absent, as in a
    // partial update, so the check lives in withValidator().
    public function test_update_cannot_move_the_end_before_the_stored_start(): void
    {
        $admin = $this->admin();
        $task = $this->task($admin, '2026-06-10 09:00:00', '2026-06-10 11:00:00');

        $this->actingAs($admin)
            ->putJson($this->adminUrl("/tasks/{$task->id}"), ['end_datetime' => '2026-06-10 08:00:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_datetime']);
    }

    public function test_update_cannot_move_the_start_after_the_stored_end(): void
    {
        $admin = $this->admin();
        $task = $this->task($admin, '2026-06-10 09:00:00', '2026-06-10 11:00:00');

        $this->actingAs($admin)
            ->putJson($this->adminUrl("/tasks/{$task->id}"), ['start_datetime' => '2026-06-10 12:00:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_datetime']);
    }

    public function test_update_can_still_clear_the_end_datetime(): void
    {
        $admin = $this->admin();
        $task = $this->task($admin, '2026-06-10 09:00:00', '2026-06-10 11:00:00');

        $this->actingAs($admin)
            ->putJson($this->adminUrl("/tasks/{$task->id}"), ['end_datetime' => null])
            ->assertOk();

        $this->assertNull($task->fresh()->end_datetime);
    }

    public function test_update_accepts_a_valid_pair(): void
    {
        $admin = $this->admin();
        $task = $this->task($admin, '2026-06-10 09:00:00', '2026-06-10 11:00:00');

        $this->actingAs($admin)
            ->putJson($this->adminUrl("/tasks/{$task->id}"), [
                'start_datetime' => '2026-06-11 09:00:00',
                'end_datetime' => '2026-06-11 10:00:00',
            ])
            ->assertOk();
    }

    public function test_a_task_cannot_be_attached_to_another_users_category(): void
    {
        $admin = $this->admin();
        $theirs = Category::create(['name' => 'Theirs', 'color' => '#c0503f', 'user_id' => User::factory()->create()->id]);

        $this->actingAs($admin)
            ->postJson($this->adminUrl('/tasks'), [
                'title' => 'Sneaky',
                'start_datetime' => '2026-06-10 09:00:00',
                'category_id' => $theirs->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    // The scoped rule must still accept the shared seeded categories.
    public function test_a_task_can_still_use_a_global_category(): void
    {
        $admin = $this->admin();
        $global = Category::create(['name' => 'Global', 'color' => '#2f75a8']);

        $this->actingAs($admin)
            ->postJson($this->adminUrl('/tasks'), [
                'title' => 'Fine',
                'start_datetime' => '2026-06-10 09:00:00',
                'category_id' => $global->id,
            ])
            ->assertCreated();
    }

    public function test_a_category_cannot_be_nested_under_another_users_category(): void
    {
        $admin = $this->admin();
        $theirs = Category::create(['name' => 'Theirs', 'color' => '#c0503f', 'user_id' => User::factory()->create()->id]);

        $this->actingAs($admin)
            ->postJson($this->adminUrl('/categories'), [
                'name' => 'Mine',
                'color' => '#7c9a6b',
                'parent_id' => $theirs->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    // One level deep: a subcategory is never a valid parent, not even your own.
    public function test_a_category_cannot_be_nested_under_a_subcategory(): void
    {
        $admin = $this->admin();
        $parent = Category::create(['name' => 'Work', 'color' => '#2f75a8', 'user_id' => $admin->id]);
        $child = Category::create(['name' => 'Deep work', 'color' => '#2f75a8', 'user_id' => $admin->id, 'parent_id' => $parent->id]);

        $this->actingAs($admin)
            ->postJson($this->adminUrl('/categories'), [
                'name' => 'Grandchild',
                'color' => '#7c9a6b',
                'parent_id' => $child->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    // The other direction: index() loads one level, so a grandchild vanishes.
    public function test_a_category_with_subcategories_cannot_be_given_a_parent(): void
    {
        $admin = $this->admin();
        $parent = Category::create(['name' => 'Work', 'color' => '#2f75a8', 'user_id' => $admin->id]);
        $child = Category::create(['name' => 'Deep work', 'color' => '#2f75a8', 'user_id' => $admin->id, 'parent_id' => $parent->id]);
        $other = Category::create(['name' => 'Life', 'color' => '#7c9a6b', 'user_id' => $admin->id]);

        $this->actingAs($admin)
            ->putJson($this->adminUrl("/categories/{$parent->id}"), [
                'name' => 'Work',
                'color' => '#2f75a8',
                'parent_id' => $other->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        $this->assertNull($parent->fresh()->parent_id);
        $this->assertSame($parent->id, $child->fresh()->parent_id);
    }

    // The guard must not block an ordinary edit of a category with children.
    public function test_a_category_with_subcategories_can_still_be_edited_in_place(): void
    {
        $admin = $this->admin();
        $parent = Category::create(['name' => 'Work', 'color' => '#2f75a8', 'user_id' => $admin->id]);
        Category::create(['name' => 'Deep work', 'color' => '#2f75a8', 'user_id' => $admin->id, 'parent_id' => $parent->id]);

        $this->actingAs($admin)
            ->putJson($this->adminUrl("/categories/{$parent->id}"), [
                'name' => 'Focus',
                'color' => '#c5a064',
                'parent_id' => null,
            ])
            ->assertOk();

        $this->assertSame('Focus', $parent->fresh()->name);
    }

    // A shared delete cascades to children and unfiles their tasks.
    public function test_a_global_category_cannot_be_deleted_out_from_under_someone_else(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();

        $global = Category::create(['name' => 'Learning', 'color' => '#c5a064']);
        $theirs = Category::create([
            'name' => 'Their subcategory',
            'color' => '#2f75a8',
            'user_id' => $other->id,
            'parent_id' => $global->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson($this->adminUrl("/categories/{$global->id}"))
            ->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $global->id]);
        $this->assertDatabaseHas('categories', ['id' => $theirs->id]);
    }

    public function test_a_global_category_can_still_be_deleted_with_only_your_own_children(): void
    {
        $admin = $this->admin();

        $global = Category::create(['name' => 'Learning', 'color' => '#c5a064']);
        Category::create([
            'name' => 'Mine',
            'color' => '#2f75a8',
            'user_id' => $admin->id,
            'parent_id' => $global->id,
        ]);
        // A shared subcategory is nobody else's to lose.
        Category::create(['name' => 'Shared', 'color' => '#2f75a8', 'parent_id' => $global->id]);

        $this->actingAs($admin)
            ->deleteJson($this->adminUrl("/categories/{$global->id}"))
            ->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $global->id]);
    }

    // Editing a shared row is recoverable; a cascading delete is not.
    public function test_a_global_category_can_still_be_edited_by_anyone(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();

        $global = Category::create(['name' => 'Learning', 'color' => '#c5a064']);
        Category::create(['name' => 'Theirs', 'color' => '#2f75a8', 'user_id' => $other->id, 'parent_id' => $global->id]);

        $this->actingAs($admin)
            ->putJson($this->adminUrl("/categories/{$global->id}"), ['name' => 'Study', 'color' => '#c5a064'])
            ->assertOk();
    }

    public function test_a_category_colour_must_be_a_hex_value(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson($this->adminUrl('/categories'), ['name' => 'Broken', 'color' => 'zzzzzzz'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['color']);

        $this->actingAs($admin)
            ->postJson($this->adminUrl('/categories'), ['name' => 'Fine', 'color' => '#2f75a8'])
            ->assertCreated();
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
        $this->assertNotNull($response->json('task.running_log.started_at'));
        $this->assertSame(1, $task->timeLogs()->whereNull('ended_at')->count());
    }

    public function test_timer_start_does_not_duplicate_an_already_running_log(): void
    {
        $admin = $this->admin();
        $task = Task::create(['user_id' => $admin->id, 'title' => 'Focus block', 'start_datetime' => '2026-06-10 09:00:00', 'status' => TaskStatus::Planned, 'source' => TaskSource::Manual]);

        $this->actingAs($admin)->postJson($this->adminUrl("/tasks/{$task->id}/timer/start"));
        $response = $this->actingAs($admin)->postJson($this->adminUrl("/tasks/{$task->id}/timer/start"));

        $response->assertOk();
        $this->assertSame(1, $task->timeLogs()->count());
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

        // The handed-over task is paused, not done, and its time is banked.
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
        // Nothing is running any more, and the closed log kept the minutes.
        $this->assertNull($response->json('task.running_log'));
        $this->assertNotNull($task->timeLogs()->first()->ended_at);
        $this->assertEqualsWithDelta(25, $task->timeLogs()->first()->duration_minutes, 1);
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

    // Every task in the range loads its category and time logs.
    public function test_the_task_range_is_capped(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson($this->adminUrl('/tasks?start=2020-01-01&end=2026-12-31'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end']);

        // A full month grid padded to whole weeks is well inside the cap.
        $this->actingAs($admin)
            ->getJson($this->adminUrl('/tasks?start=2026-06-01&end=2026-07-12'))
            ->assertOk();
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
        $this->assertSame($category->id, $response->json('by_category.0.category_id'));
        $this->assertSame(45, $response->json('by_category.0.minutes'));
    }

    // A global and a personal category can share a name, so grouping is by id.
    public function test_report_keeps_same_named_categories_apart(): void
    {
        $admin = $this->admin();
        $global = Category::create(['name' => 'Admin', 'color' => '#c5a064']);
        $mine = Category::create(['name' => 'Admin', 'color' => '#2f75a8', 'user_id' => $admin->id]);

        foreach ([$global, $mine] as $category) {
            $task = Task::create([
                'user_id' => $admin->id,
                'category_id' => $category->id,
                'title' => 'Paperwork',
                'start_datetime' => '2026-06-10 09:00:00',
                'status' => TaskStatus::Done,
                'source' => TaskSource::Manual,
            ]);
            $task->timeLogs()->create(['started_at' => '2026-06-10 09:00:00', 'ended_at' => '2026-06-10 09:30:00']);
        }

        $rows = $this->actingAs($admin)->getJson($this->adminUrl('/reports?').http_build_query([
            'period_type' => 'week',
            'period_start' => '2026-06-08',
        ]))->assertOk()->json('by_category');

        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(
            [$global->id, $mine->id],
            array_column($rows, 'category_id'),
        );
        $this->assertEqualsCanonicalizing(
            ['#c5a064', '#2f75a8'],
            array_column($rows, 'color'),
        );
    }

    // Labelled by the frontend, so the report returns no English.
    public function test_report_returns_a_null_category_bucket_rather_than_a_label(): void
    {
        $admin = $this->admin();
        $task = $this->task($admin, '2026-06-10 09:00:00');
        $task->timeLogs()->create(['started_at' => '2026-06-10 09:00:00', 'ended_at' => '2026-06-10 09:20:00']);

        $rows = $this->actingAs($admin)->getJson($this->adminUrl('/reports?').http_build_query([
            'period_type' => 'week',
            'period_start' => '2026-06-08',
        ]))->assertOk()->json('by_category');

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['category_id']);
        $this->assertNull($rows[0]['category']);
        $this->assertSame(20, $rows[0]['minutes']);
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

    // The number is "minutes tracked this week", not "every minute ever spent
    // on a task that happens to start this week".
    public function test_report_counts_only_the_minutes_logged_inside_the_period(): void
    {
        $admin = $this->admin();

        $task = Task::create([
            'user_id' => $admin->id,
            'title' => 'Long runner',
            'start_datetime' => '2026-06-10 09:00:00',
            'status' => TaskStatus::InProgress,
            'source' => TaskSource::Manual,
        ]);

        $task->timeLogs()->create(['started_at' => '2026-06-10 09:00:00', 'ended_at' => '2026-06-10 09:45:00']);
        // Same task, the week before: its minutes belong to that week.
        $task->timeLogs()->create(['started_at' => '2026-06-03 09:00:00', 'ended_at' => '2026-06-03 11:00:00']);

        $response = $this->actingAs($admin)->getJson($this->adminUrl('/reports?').http_build_query([
            'period_type' => 'week',
            'period_start' => '2026-06-08',
        ]));

        $response->assertOk();
        $this->assertSame(45, $response->json('total_minutes'));
    }

    // The other half of the same rule: time tracked here is never invisible
    // because the task it belongs to was scheduled in another week.
    public function test_report_includes_time_tracked_on_a_task_scheduled_elsewhere(): void
    {
        $admin = $this->admin();

        $task = Task::create([
            'user_id' => $admin->id,
            'title' => 'Started last week',
            'start_datetime' => '2026-06-03 09:00:00',
            'end_datetime' => '2026-06-03 10:00:00',
            'status' => TaskStatus::InProgress,
            'source' => TaskSource::Manual,
        ]);

        $task->timeLogs()->create(['started_at' => '2026-06-10 09:00:00', 'ended_at' => '2026-06-10 09:20:00']);

        $response = $this->actingAs($admin)->getJson($this->adminUrl('/reports?').http_build_query([
            'period_type' => 'week',
            'period_start' => '2026-06-08',
        ]));

        $response->assertOk();
        $this->assertSame(20, $response->json('total_minutes'));
        // Planned time stays with the week the task was scheduled in.
        $this->assertSame(0, $response->json('total_planned_minutes'));
    }

    // A date mid-period names its period, rather than reporting from that day
    // to the end of the week and calling it a week.
    public function test_report_normalises_the_period_start(): void
    {
        $admin = $this->admin();

        $task = $this->task($admin, '2026-06-08 09:00:00');
        $task->timeLogs()->create(['started_at' => '2026-06-08 09:00:00', 'ended_at' => '2026-06-08 09:30:00']);

        $response = $this->actingAs($admin)->getJson($this->adminUrl('/reports?').http_build_query([
            'period_type' => 'week',
            // A Wednesday, three days after the Monday the week really starts.
            'period_start' => '2026-06-10',
        ]));

        $response->assertOk();
        $this->assertSame('2026-06-08', $response->json('period_start'));
        $this->assertSame('2026-06-14', $response->json('period_end'));
        $this->assertSame(30, $response->json('total_minutes'));
    }

    // One week, one reflection — whichever day of it the caller names.
    public function test_reflections_key_on_the_first_day_of_the_period(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson($this->adminUrl('/reflections'), [
            'period_type' => 'week',
            'period_start' => '2026-06-08',
            'notes' => 'Monday.',
        ])->assertOk();

        $this->actingAs($admin)->putJson($this->adminUrl('/reflections'), [
            'period_type' => 'week',
            'period_start' => '2026-06-11',
            'notes' => 'Thursday, same week.',
        ])->assertOk();

        $this->assertSame(1, Reflection::query()->count());
        $this->assertSame('Thursday, same week.', Reflection::query()->value('notes'));
    }

    // 'date' accepts far more than the Y-m-d the calendar sends.
    public function test_task_index_accepts_a_date_it_has_to_parse(): void
    {
        $admin = $this->admin();
        $this->task($admin, '2026-06-10 09:00:00');

        $tasks = $this->actingAs($admin)
            ->getJson($this->adminUrl('/tasks?').http_build_query([
                'start' => '8 June 2026',
                'end' => '14 June 2026',
            ]))
            ->assertOk()
            ->json('tasks');

        $this->assertCount(1, $tasks);
    }

    // A global parent is shared by everyone, so its children must still be
    // filtered — otherwise one user's private subcategories reach another's.
    public function test_the_category_list_hides_another_users_subcategories(): void
    {
        $admin = $this->admin();
        $stranger = User::factory()->create();

        $global = Category::create(['name' => 'Work', 'color' => '#c5a064']);
        Category::create(['name' => 'Mine', 'color' => '#2f75a8', 'user_id' => $admin->id, 'parent_id' => $global->id]);
        Category::create(['name' => 'Theirs', 'color' => '#2f75a8', 'user_id' => $stranger->id, 'parent_id' => $global->id]);

        $categories = $this->actingAs($admin)
            ->getJson($this->adminUrl('/categories'))
            ->assertOk()
            ->json('categories');

        $names = collect($categories)->firstWhere('id', $global->id)['children'];

        $this->assertSame(['Mine'], collect($names)->pluck('name')->all());
    }

    // A global subcategory is shared like its parent.
    public function test_the_category_list_keeps_global_subcategories(): void
    {
        $admin = $this->admin();

        $global = Category::create(['name' => 'Work', 'color' => '#c5a064']);
        Category::create(['name' => 'Shared child', 'color' => '#c5a064', 'parent_id' => $global->id]);

        $categories = $this->actingAs($admin)
            ->getJson($this->adminUrl('/categories'))
            ->assertOk()
            ->json('categories');

        $children = collect($categories)->firstWhere('id', $global->id)['children'];

        $this->assertSame(['Shared child'], collect($children)->pluck('name')->all());
    }
}
