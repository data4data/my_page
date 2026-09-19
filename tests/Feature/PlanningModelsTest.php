<?php

namespace Tests\Feature;

use App\Enums\ReflectionPeriodType;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\TimeLog;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoWeekSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlanningModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_supports_one_level_of_nesting(): void
    {
        $parent = Category::create(['name' => 'Learning', 'color' => '#c5a064']);
        $child = Category::create(['name' => 'Laravel', 'color' => '#c5a064', 'parent_id' => $parent->id]);

        $this->assertTrue($parent->children->contains($child));
        $this->assertTrue($child->parent->is($parent));
    }

    public function test_task_casts_status_and_source_to_enums_and_datetime_to_carbon(): void
    {
        $user = User::factory()->create();

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Write tests',
            'start_datetime' => '2026-08-17 09:00:00',
            'status' => TaskStatus::Planned,
            'source' => TaskSource::Manual,
        ]);

        $task->refresh();

        $this->assertSame(TaskStatus::Planned, $task->status);
        $this->assertSame(TaskSource::Manual, $task->source);
        $this->assertTrue($task->start_datetime->isSameMinute('2026-08-17 09:00:00'));
    }

    /**
     * duration_minutes is a generated column now, so the database fills it
     * and the model instance that did the insert does not know it yet —
     * ->refresh() is what reads it back. Nothing in the app needs it in
     * memory: reports sum it in SQL.
     */
    public function test_the_database_derives_duration_minutes(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Focus block',
            'start_datetime' => '2026-08-17 09:00:00',
            'status' => TaskStatus::InProgress,
            'source' => TaskSource::Manual,
        ]);

        $log = TimeLog::create([
            'task_id' => $task->id,
            'started_at' => '2026-08-17 09:00:00',
            'ended_at' => '2026-08-17 09:37:00',
        ]);

        $this->assertSame(37, $log->refresh()->duration_minutes);
    }

    public function test_time_log_leaves_duration_null_until_stopped(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Running timer',
            'start_datetime' => '2026-08-17 09:00:00',
            'status' => TaskStatus::InProgress,
            'source' => TaskSource::Manual,
        ]);

        $log = TimeLog::create([
            'task_id' => $task->id,
            'started_at' => '2026-08-17 09:00:00',
        ]);

        $this->assertNull($log->duration_minutes);
    }

    // GREATEST(..., 0) in the generated expression: a backwards pair would be
    // rejected by MySQL in strict mode.
    public function test_a_backwards_time_log_never_stores_a_negative_duration(): void
    {
        $task = Task::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Backwards',
            'start_datetime' => '2026-06-10 09:00:00',
            'status' => TaskStatus::Planned,
            'source' => TaskSource::Manual,
        ]);

        $log = $task->timeLogs()->create([
            'started_at' => '2026-06-10 10:00:00',
            'ended_at' => '2026-06-10 09:00:00',
        ]);

        $this->assertSame(0, $log->refresh()->duration_minutes);
    }

    public function test_reopening_a_log_clears_its_duration(): void
    {
        $task = Task::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Reopened',
            'start_datetime' => '2026-06-10 09:00:00',
            'status' => TaskStatus::Planned,
            'source' => TaskSource::Manual,
        ]);

        $log = $task->timeLogs()->create([
            'started_at' => '2026-06-10 09:00:00',
            'ended_at' => '2026-06-10 09:30:00',
        ]);
        $this->assertSame(30, $log->refresh()->duration_minutes);

        $log->update(['ended_at' => null]);

        $this->assertNull($log->fresh()->duration_minutes);
    }

    public function test_reflection_period_is_unique_per_user(): void
    {
        $user = User::factory()->create();

        Reflection::create([
            'user_id' => $user->id,
            'period_type' => ReflectionPeriodType::Week,
            'period_start' => '2026-08-17',
            'period_end' => '2026-08-23',
        ]);

        $this->expectException(QueryException::class);

        Reflection::create([
            'user_id' => $user->id,
            'period_type' => ReflectionPeriodType::Week,
            'period_start' => '2026-08-17',
            'period_end' => '2026-08-23',
        ]);
    }

    public function test_category_seeder_is_idempotent(): void
    {
        (new CategorySeeder)->run();
        $firstRunCount = Category::count();

        (new CategorySeeder)->run();

        $this->assertSame($firstRunCount, Category::count());
        $this->assertGreaterThan(0, Category::whereNotNull('parent_id')->count());
    }

    public function test_demo_week_seeder_refreshes_instead_of_duplicating_on_rerun(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        (new CategorySeeder)->run();
        (new DemoWeekSeeder)->run();
        $firstRunCount = Task::count();

        (new DemoWeekSeeder)->run();

        $this->assertSame($firstRunCount, Task::count());
        $this->assertTrue(Task::where('source', TaskSource::Seeder)->count() === $firstRunCount);
    }

    public function test_demo_week_seeder_skips_gracefully_without_an_admin_user(): void
    {
        (new CategorySeeder)->run();
        (new DemoWeekSeeder)->run();

        $this->assertSame(0, Task::count());
    }
}
