<?php

namespace Tests\Feature;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\TimerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The "only one timer runs at a time" rule used to be a private controller
 * method, reachable only through an HTTP POST. These call it directly — no
 * route, no session, no request — which is the whole reason it was extracted.
 */
class TimerServiceTest extends TestCase
{
    use RefreshDatabase;

    private TimerService $timer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timer = app(TimerService::class);
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

    public function test_starting_opens_a_log_and_flips_the_status(): void
    {
        $task = $this->task(User::factory()->create());

        $result = $this->timer->start($task);

        $this->assertSame(TaskStatus::InProgress, $result->status);
        $this->assertCount(1, $result->timeLogs);
        $this->assertNull($result->timeLogs->first()->ended_at);
    }

    public function test_starting_twice_does_not_open_a_second_log(): void
    {
        $task = $this->task(User::factory()->create());

        $this->timer->start($task);
        $result = $this->timer->start($task->fresh());

        $this->assertCount(1, $result->timeLogs);
    }

    /**
     * Without this the same minutes count against several tasks at once and
     * every report total overstates the day.
     */
    public function test_starting_stops_and_pauses_any_other_running_task(): void
    {
        $user = User::factory()->create();
        $first = $this->task($user, 'First');
        $second = $this->task($user, 'Second');

        $first->timeLogs()->create(['started_at' => Carbon::now()->subMinutes(30)]);
        $first->update(['status' => TaskStatus::InProgress]);

        $this->timer->start($second);

        $first = $first->fresh(['timeLogs']);
        $this->assertSame(TaskStatus::Paused, $first->status);
        $this->assertNotNull($first->timeLogs->first()->ended_at);
    }

    /**
     * The closing write goes through the model one row at a time so
     * TimeLog::booted()'s saving hook still computes duration_minutes. A mass
     * builder update() would leave this null.
     */
    public function test_the_interrupted_log_still_gets_its_duration_computed(): void
    {
        $user = User::factory()->create();
        $first = $this->task($user, 'First');
        $second = $this->task($user, 'Second');

        $first->timeLogs()->create(['started_at' => Carbon::now()->subMinutes(30)]);

        $this->timer->start($second);

        $this->assertSame(30, $first->fresh(['timeLogs'])->timeLogs->first()->duration_minutes);
    }

    public function test_another_users_running_task_is_left_alone(): void
    {
        $mine = $this->task(User::factory()->create(), 'Mine');
        $theirs = $this->task(User::factory()->create(), 'Theirs');

        $theirs->timeLogs()->create(['started_at' => Carbon::now()->subMinutes(10)]);
        $theirs->update(['status' => TaskStatus::InProgress]);

        $this->timer->start($mine);

        $theirs = $theirs->fresh(['timeLogs']);
        $this->assertSame(TaskStatus::InProgress, $theirs->status);
        $this->assertNull($theirs->timeLogs->first()->ended_at);
    }

    public function test_stopping_closes_the_log_with_a_duration(): void
    {
        $task = $this->task(User::factory()->create());
        $task->timeLogs()->create(['started_at' => Carbon::now()->subMinutes(25)]);

        $result = $this->timer->stop($task);

        $log = $result->timeLogs->first();
        $this->assertNotNull($log->ended_at);
        $this->assertSame(25, $log->duration_minutes);
    }

    public function test_stopping_when_nothing_runs_is_a_safe_no_op(): void
    {
        $task = $this->task(User::factory()->create());

        $result = $this->timer->stop($task);

        $this->assertCount(0, $result->timeLogs);
    }
}
