<?php

namespace Tests\Unit;

use App\Models\Task;
use Tests\TestCase;

// No database: plannedMinutes() only reads attributes, so these run against
// unsaved instances. Tests\TestCase, because the 'datetime' cast needs the
// container.
class TaskPlannedMinutesTest extends TestCase
{
    public function test_an_explicit_planned_duration_wins(): void
    {
        $task = new Task([
            'planned_duration_minutes' => 45,
            // Deliberately contradicts the span below, to prove which one is used.
            'start_datetime' => '2026-08-27 09:00:00',
            'end_datetime' => '2026-08-27 12:00:00',
        ]);

        $this->assertSame(45, $task->plannedMinutes());
    }

    public function test_it_falls_back_to_the_scheduled_span(): void
    {
        $task = new Task([
            'start_datetime' => '2026-08-27 09:00:00',
            'end_datetime' => '2026-08-27 10:30:00',
        ]);

        $this->assertSame(90, $task->plannedMinutes());
    }

    public function test_an_open_ended_task_is_zero(): void
    {
        $task = new Task([
            'start_datetime' => '2026-08-27 09:00:00',
        ]);

        $this->assertSame(0, $task->plannedMinutes());
    }

    public function test_a_zero_planned_duration_is_kept_rather_than_falling_back(): void
    {
        $task = new Task([
            'planned_duration_minutes' => 0,
            'start_datetime' => '2026-08-27 09:00:00',
            'end_datetime' => '2026-08-27 10:00:00',
        ]);

        $this->assertSame(0, $task->plannedMinutes());
    }

    public function test_an_end_before_the_start_never_goes_negative(): void
    {
        $task = new Task([
            'start_datetime' => '2026-08-27 10:00:00',
            'end_datetime' => '2026-08-27 09:00:00',
        ]);

        $this->assertSame(0, $task->plannedMinutes());
    }
}
