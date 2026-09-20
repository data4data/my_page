<?php

namespace Tests\Feature;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Events\InquiryReceived;
use App\Events\PortfolioSaved;
use App\Events\TimerStarted;
use App\Events\TimerStopped;
use App\Models\PortfolioRevision;
use App\Models\Task;
use App\Models\User;
use App\Notifications\InquiryReceivedNotification;
use App\Services\PortfolioSeeder;
use App\Services\TimerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The three things that are easy to get wrong: that events fire at all, that
 * they fire after the transaction, and that they do not fire for a no-op.
 */
class EventsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function task(User $user): Task
    {
        return Task::create([
            'user_id' => $user->id,
            'title' => 'Task',
            'start_datetime' => '2026-06-10 09:00:00',
            'status' => TaskStatus::Planned,
            'source' => TaskSource::Manual,
        ]);
    }

    public function test_saving_the_page_announces_it_once_the_write_has_committed(): void
    {
        app(PortfolioSeeder::class)->seed();
        $admin = $this->admin();

        Event::fake([PortfolioSaved::class]);

        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), [
            'profile' => ['initials' => 'ZZ'],
            'metrics' => [],
            'expertise_items' => [],
            'projects' => [],
            'process_steps' => [],
            'social_links' => [],
        ])->assertOk();

        Event::assertDispatched(PortfolioSaved::class, function (PortfolioSaved $event) use ($admin) {
            // Committed, not pending: a rollback must not undo what a listener read.
            $this->assertSame('ZZ', $event->profile->fresh()->initials);

            return $event->author?->is($admin) && $event->restored === false;
        });
    }

    /** A restore is a save through the same path, told apart by a flag. */
    public function test_a_restore_says_so(): void
    {
        app(PortfolioSeeder::class)->seed();
        $admin = $this->admin();

        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), [
            'profile' => ['initials' => 'AA'],
            'metrics' => [], 'expertise_items' => [], 'projects' => [], 'process_steps' => [], 'social_links' => [],
        ])->assertOk();

        $revision = PortfolioRevision::query()->orderBy('id')->firstOrFail();

        Event::fake([PortfolioSaved::class]);

        $this->actingAs($admin)
            ->postJson($this->adminUrl("/portfolio/revisions/{$revision->id}/restore"))
            ->assertOk();

        Event::assertDispatched(PortfolioSaved::class, fn (PortfolioSaved $event) => $event->restored === true);
    }

    public function test_the_connect_form_announces_a_new_message(): void
    {
        Event::fake([InquiryReceived::class]);

        $this->postJson('/hi-developer', [
            'name' => 'Sam',
            'email' => 'sam@example.test',
            'message' => 'Hello there, this is a real message.',
        ])->assertOk();

        Event::assertDispatched(InquiryReceived::class, fn ($event) => $event->inquiry->name === 'Sam');
    }

    public function test_the_owner_is_mailed_the_message(): void
    {
        $owner = $this->admin();
        Notification::fake();

        $this->postJson('/hi-developer', [
            'name' => 'Sam',
            'email' => 'sam@example.test',
            'message' => 'Hello there, this is a real message.',
        ])->assertOk();

        Notification::assertSentTo($owner, InquiryReceivedNotification::class);
    }

    // No admin and no `admin` role yet: a stranger's message must still land.
    public function test_a_message_is_still_accepted_with_nobody_to_mail(): void
    {
        Notification::fake();

        $this->postJson('/hi-developer', [
            'name' => 'Sam',
            'email' => 'sam@example.test',
            'message' => 'Hello there, this is a real message.',
        ])->assertOk();

        $this->assertDatabaseHas('developer_inquiries', ['email' => 'sam@example.test']);
        Notification::assertNothingSent();
    }

    public function test_starting_and_stopping_a_timer_are_announced(): void
    {
        $user = $this->admin();
        $task = $this->task($user);

        Event::fake([TimerStarted::class, TimerStopped::class]);

        app(TimerService::class)->start($task);
        Event::assertDispatched(TimerStarted::class);

        app(TimerService::class)->stop($task);
        Event::assertDispatched(TimerStopped::class);
    }

    /** Calling either twice is a no-op, and a no-op is not news. */
    public function test_a_second_start_or_stop_announces_nothing(): void
    {
        $user = $this->admin();
        $task = $this->task($user);

        app(TimerService::class)->start($task);

        Event::fake([TimerStarted::class, TimerStopped::class]);

        app(TimerService::class)->start($task->fresh());
        Event::assertNotDispatched(TimerStarted::class);

        app(TimerService::class)->stop($task->fresh());
        app(TimerService::class)->stop($task->fresh());
        Event::assertDispatchedTimes(TimerStopped::class, 1);
    }
}
