<?php

namespace Tests\Feature;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What a task looks like on the way out. The claim is the hardcoded key list
 * below: a column added to the table joins the response when someone puts it
 * in TaskResource, and not before.
 */
class TaskPayloadShapeTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = [
        'id',
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'planned_duration_minutes',
        'status',
        'result_notes',
        'source',
        'category_id',
        'category',
        'running_log',
    ];

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

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

    private function index(User $user): array
    {
        return $this->actingAs($user)
            ->getJson($this->adminUrl('/tasks?start=2026-06-08&end=2026-06-14'))
            ->assertOk()
            ->json('tasks');
    }

    public function test_a_task_carries_exactly_the_fields_the_board_reads(): void
    {
        $admin = $this->admin();
        $this->task($admin);

        $this->assertEqualsCanonicalizing(self::KEYS, array_keys($this->index($admin)[0]));
    }

    /** Ownership, position and timestamps are nobody's business but the server's. */
    public function test_a_task_ships_no_metadata(): void
    {
        $admin = $this->admin();
        $this->task($admin);

        foreach (['user_id', 'sort_order', 'external_ref', 'created_at', 'updated_at'] as $key) {
            $this->assertArrayNotHasKey($key, $this->index($admin)[0]);
        }
    }

    public function test_a_running_timer_travels_as_one_log_rather_than_a_history(): void
    {
        $admin = $this->admin();
        $task = $this->task($admin);

        // Three finished sittings and one still running.
        foreach ([[90, 60], [50, 40], [30, 20]] as [$from, $to]) {
            $task->timeLogs()->create([
                'started_at' => Carbon::now()->subMinutes($from),
                'ended_at' => Carbon::now()->subMinutes($to),
            ]);
        }

        $open = $task->timeLogs()->create(['started_at' => Carbon::now()->subMinutes(5)]);

        $payload = $this->index($admin)[0];

        $this->assertSame($open->id, $payload['running_log']['id']);
        $this->assertEqualsCanonicalizing(['id', 'started_at'], array_keys($payload['running_log']));
    }

    public function test_a_task_with_no_timer_running_says_so_rather_than_sending_its_closed_logs(): void
    {
        $admin = $this->admin();
        $task = $this->task($admin);
        $task->timeLogs()->create([
            'started_at' => Carbon::now()->subMinutes(60),
            'ended_at' => Carbon::now()->subMinutes(30),
        ]);

        $payload = $this->index($admin)[0];

        $this->assertNull($payload['running_log']);
        $this->assertArrayNotHasKey('time_logs', $payload);
    }

    /** The category is what colours a card, so it travels with the task. */
    public function test_the_category_still_travels_with_the_task(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Learning', 'color' => '#c5a064']);
        $this->task($admin)->update(['category_id' => $category->id]);

        $this->assertSame('#c5a064', $this->index($admin)[0]['category']['color']);
    }

    /**
     * A month grid costs the same number of queries as a single day. Eager
     * loading is the whole reason the range cap is survivable.
     */
    public function test_the_range_costs_the_same_number_of_queries_however_many_tasks_it_holds(): void
    {
        $admin = $this->admin();
        $this->task($admin);

        $count = fn () => count(DB::getRawQueryLog());

        DB::enableQueryLog();
        // Discarded: the first request of a test also warms the permission
        // cache, which is not part of what a range costs.
        $this->index($admin);

        DB::flushQueryLog();
        $this->index($admin);
        $one = $count();

        foreach (range(1, 20) as $number) {
            $this->task($admin, "Task {$number}");
        }

        DB::flushQueryLog();
        $this->index($admin);

        $this->assertSame($one, $count());
    }
}
