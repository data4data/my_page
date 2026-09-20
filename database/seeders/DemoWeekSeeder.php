<?php

namespace Database\Seeders;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoWeekSeeder extends Seeder
{
    /**
     * A sample week, so a fresh clone shows a working board. Local only —
     * DatabaseSeeder guards the environment.
     */
    public function run(): void
    {
        // whereHas, not the role() scope, which throws when no role exists yet.
        $user = User::whereHas('roles', fn ($query) => $query->where('name', 'admin'))->first();

        if (! $user) {
            $this->command?->warn('DemoWeekSeeder: no admin user yet — run `php artisan app:install` first. Skipping.');

            return;
        }

        // Pinned to this week, so a re-run refreshes rather than stacks.
        Task::query()->where('user_id', $user->id)->where('source', TaskSource::Seeder)->delete();

        // Global categories only: a personal one sharing a name would make the
        // sample week differ between installs.
        $categoryByName = fn (string $name) => Category::query()
            ->where('name', $name)
            ->whereNull('user_id')
            ->value('id');

        $monday = Carbon::now()->startOfWeek();

        // Generic on purpose, and between them they cover all five statuses,
        // so a fresh install shows every state the board can be in.
        $tasks = [
            [
                'title' => 'Focus block',
                'category_id' => $categoryByName('Work'),
                'day' => 0,
                'start' => '09:00',
                'duration' => 90,
                'status' => TaskStatus::Done,
                'result_notes' => 'Cleared the first big item of the week.',
                'logged_minutes' => 95,
            ],
            [
                'title' => 'Evening walk',
                'category_id' => $categoryByName('Health'),
                'day' => 0,
                'start' => '18:00',
                'duration' => 45,
                'status' => TaskStatus::Done,
                'result_notes' => 'Easy pace.',
                'logged_minutes' => 42,
            ],
            [
                'title' => 'Read a chapter',
                'category_id' => $categoryByName('Reading'),
                'day' => 1,
                'start' => '10:00',
                'duration' => 60,
                'status' => TaskStatus::InProgress,
                'result_notes' => null,
                'logged_minutes' => 20,
            ],
            [
                'title' => 'Weekly shop',
                'category_id' => $categoryByName('Home'),
                'day' => 2,
                'start' => '18:30',
                'duration' => 40,
                'status' => TaskStatus::Planned,
                'result_notes' => null,
                'logged_minutes' => null,
            ],
            [
                'title' => 'Plan the next milestone',
                'category_id' => $categoryByName('Planning'),
                'day' => 3,
                'start' => '14:00',
                'duration' => 60,
                'status' => TaskStatus::Paused,
                'result_notes' => 'Picked up again after the call.',
                'logged_minutes' => 25,
            ],
            [
                'title' => 'Language practice',
                'category_id' => $categoryByName('Practice'),
                'day' => 4,
                'start' => '08:30',
                'duration' => 30,
                'status' => TaskStatus::Skipped,
                'result_notes' => null,
                'logged_minutes' => null,
            ],
        ];

        foreach ($tasks as $index => $task) {
            $start = $monday->copy()->addDays($task['day'])->setTimeFromTimeString($task['start']);
            $end = $start->copy()->addMinutes($task['duration']);

            /** @var Task $record */
            $record = Task::query()->create([
                'user_id' => $user->id,
                'category_id' => $task['category_id'],
                'title' => $task['title'],
                'start_datetime' => $start,
                'end_datetime' => $end,
                'planned_duration_minutes' => $task['duration'],
                'status' => $task['status'],
                'sort_order' => $index,
                'result_notes' => $task['result_notes'],
                'source' => TaskSource::Seeder,
            ]);

            if ($task['logged_minutes'] !== null) {
                $record->timeLogs()->create([
                    'user_id' => $user->id,
                    'started_at' => $start,
                    'ended_at' => $start->copy()->addMinutes($task['logged_minutes']),
                ]);
            }
        }
    }
}
