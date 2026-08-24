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
     * A populated sample week, purely so anyone cloning the repo sees a
     * working board right after `php artisan migrate --seed` without first
     * having to understand the schema. Never call this outside local — see
     * the environment guard in DatabaseSeeder::run().
     */
    public function run(): void
    {
        // whereHas (not the role() scope) so a missing 'admin' role just
        // yields no match instead of Spatie throwing RoleDoesNotExist.
        $user = User::whereHas('roles', fn ($query) => $query->where('name', 'admin'))->first();

        if (! $user) {
            $this->command?->warn('DemoWeekSeeder: no admin user found yet — run AdminUserSeeder first. Skipping.');

            return;
        }

        // Demo tasks are pinned to "this week", so a re-run (e.g. re-seeding
        // days later) should refresh them to the new week rather than stack
        // duplicates on top of last time's rows.
        Task::query()->where('user_id', $user->id)->where('source', TaskSource::Seeder)->delete();

        $categoryByName = fn (string $name) => Category::query()->where('name', $name)->value('id');

        $monday = Carbon::now()->startOfWeek();

        $tasks = [
            [
                'title' => 'Deep learning: Laravel queues',
                'category_id' => $categoryByName('Laravel'),
                'day' => 0,
                'start' => '09:00',
                'duration' => 90,
                'status' => TaskStatus::Done,
                'result_notes' => 'Worked through queued jobs and batching; wired a test queue locally.',
                'logged_minutes' => 95,
            ],
            [
                'title' => 'Sport: run',
                'category_id' => $categoryByName('Sport'),
                'day' => 0,
                'start' => '18:00',
                'duration' => 45,
                'status' => TaskStatus::Done,
                'result_notes' => '5k, easy pace.',
                'logged_minutes' => 42,
            ],
            [
                'title' => 'Job search: LinkedIn outreach',
                'category_id' => $categoryByName('Job Search'),
                'day' => 1,
                'start' => '10:00',
                'duration' => 60,
                'status' => TaskStatus::InProgress,
                'result_notes' => null,
                'logged_minutes' => 20,
            ],
            [
                'title' => 'Cook dinner',
                'category_id' => $categoryByName('Household'),
                'day' => 2,
                'start' => '18:30',
                'duration' => 40,
                'status' => TaskStatus::Planned,
                'result_notes' => null,
                'logged_minutes' => null,
            ],
            [
                'title' => 'Interview prep: mock interview',
                'category_id' => $categoryByName('Interview Prep'),
                'day' => 3,
                'start' => '14:00',
                'duration' => 60,
                'status' => TaskStatus::Planned,
                'result_notes' => null,
                'logged_minutes' => null,
            ],
            [
                'title' => 'Dutch: vocabulary practice',
                'category_id' => $categoryByName('Dutch'),
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
                    'started_at' => $start,
                    'ended_at' => $start->copy()->addMinutes($task['logged_minutes']),
                ]);
            }
        }
    }
}
