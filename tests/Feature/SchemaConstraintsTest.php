<?php

namespace Tests\Feature;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rules the database holds, rather than only the application: a migration
 * rewritten without one fails here rather than a year later in a report.
 */
class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $user, string $title = 'Task'): Task
    {
        return Task::create([
            'user_id' => $user->id,
            'title' => $title,
            'start_datetime' => '2026-06-10 09:00:00',
            'status' => TaskStatus::Planned,
            'source' => TaskSource::Manual,
        ]);
    }

    public function test_a_user_cannot_have_two_timers_running(): void
    {
        $user = User::factory()->create();
        $first = $this->task($user, 'First');
        $second = $this->task($user, 'Second');

        $first->timeLogs()->create(['started_at' => '2026-06-10 09:00:00']);

        $this->expectException(QueryException::class);
        $second->timeLogs()->create(['started_at' => '2026-06-10 09:30:00']);
    }

    public function test_two_users_may_each_have_one_running(): void
    {
        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            $this->task($user)->timeLogs()->create(['started_at' => '2026-06-10 09:00:00']);
        }

        $this->assertSame(2, TimeLog::whereNull('ended_at')->count());
    }

    /** Closed logs hold NULL, and a unique index does not compare NULLs. */
    public function test_any_number_of_finished_logs_coexist(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user);

        foreach ([['09:00', '09:30'], ['10:00', '10:30'], ['11:00', '11:30']] as [$from, $to]) {
            $task->timeLogs()->create([
                'started_at' => "2026-06-10 {$from}:00",
                'ended_at' => "2026-06-10 {$to}:00",
            ]);
        }

        $this->assertSame(3, $task->timeLogs()->count());
        $task->timeLogs()->create(['started_at' => '2026-06-10 12:00:00']);
        $this->assertSame(4, $task->timeLogs()->count());
    }

    public function test_a_log_takes_its_owner_from_its_task(): void
    {
        $user = User::factory()->create();
        $log = $this->task($user)->timeLogs()->create(['started_at' => '2026-06-10 09:00:00']);

        $this->assertSame($user->id, $log->refresh()->user_id);
    }

    public function test_the_same_remote_event_cannot_be_imported_twice(): void
    {
        $user = User::factory()->create();

        $row = [
            'user_id' => $user->id,
            'title' => 'Standup',
            'start_datetime' => '2026-06-10 09:00:00',
            'status' => TaskStatus::Planned,
            'source' => TaskSource::Seeder,
            'external_ref' => 'event-1',
        ];

        Task::create($row);

        $this->expectException(QueryException::class);
        Task::create($row);
    }

    /** Manual tasks carry no external_ref, and NULLs never collide. */
    public function test_manual_tasks_are_unaffected_by_that_index(): void
    {
        $user = User::factory()->create();

        foreach (['One', 'Two', 'Three'] as $title) {
            $this->task($user, $title);
        }

        $this->assertSame(3, Task::where('user_id', $user->id)->count());
    }

    public function test_a_period_end_is_derived_not_supplied(): void
    {
        $user = User::factory()->create();

        $week = Reflection::create([
            'user_id' => $user->id,
            'period_type' => 'week',
            'period_start' => '2026-06-08',
            'notes' => 'Week note',
        ]);

        $month = Reflection::create([
            'user_id' => $user->id,
            'period_type' => 'month',
            'period_start' => '2026-06-01',
            'notes' => 'Month note',
        ]);

        $this->assertSame('2026-06-14', $week->refresh()->period_end->toDateString());
        $this->assertSame('2026-06-30', $month->refresh()->period_end->toDateString());
    }
}
