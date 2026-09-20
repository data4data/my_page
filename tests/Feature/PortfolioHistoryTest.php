<?php

namespace Tests\Feature;

use App\Models\PortfolioProfile;
use App\Models\PortfolioRevision;
use App\Models\User;
use App\Services\PortfolioContentService;
use App\Services\PortfolioSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortfolioHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function seededProfile(): PortfolioProfile
    {
        return app(PortfolioSeeder::class)->seed();
    }

    private function payload(string $headline): array
    {
        return [
            'profile' => [
                'initials' => 'AB',
                'role' => ['en' => 'Developer', 'nl' => 'Ontwikkelaar'],
                'headline' => ['en' => $headline, 'nl' => $headline],
                'summary' => ['en' => 'Summary', 'nl' => 'Samenvatting'],
                'default_language' => 'en',
                'show_language_toggle' => true,
            ],
            'metrics' => [
                ['value' => '5+', 'label' => ['en' => 'Years', 'nl' => 'Jaar'], 'is_visible' => true],
            ],
            'expertise_items' => [],
            'projects' => [],
            'process_steps' => [],
        ];
    }

    // The first save also records where the page started, so it is undoable.
    public function test_the_first_save_records_a_baseline_and_the_new_state(): void
    {
        $profile = $this->seededProfile();

        $this->actingAs($this->admin())
            ->putJson($this->adminUrl('/portfolio'), $this->payload('First edit'))
            ->assertOk();

        $this->assertSame(2, $profile->revisions()->count());
    }

    public function test_each_later_save_adds_exactly_one_revision(): void
    {
        $profile = $this->seededProfile();
        $admin = $this->admin();

        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $this->payload('One'))->assertOk();
        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $this->payload('Two'))->assertOk();

        // baseline + two saves
        $this->assertSame(3, $profile->revisions()->count());
    }

    public function test_the_list_reports_when_and_by_whom_without_the_payload(): void
    {
        $this->seededProfile();
        $admin = $this->admin();

        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $this->payload('Edited'))->assertOk();

        $response = $this->actingAs($admin)->getJson($this->adminUrl('/portfolio/revisions'));

        $response->assertOk();
        $revisions = $response->json('revisions');

        $this->assertCount(2, $revisions);
        // Newest first: the save is the admin's, the baseline has no author.
        $this->assertSame($admin->name, $revisions[0]['author']);
        $this->assertNull($revisions[1]['author']);
        $this->assertNotNull($revisions[0]['created_at']);
        $this->assertArrayNotHasKey('payload', $revisions[0]);
    }

    public function test_restoring_brings_the_old_content_back(): void
    {
        $this->seededProfile();
        $admin = $this->admin();

        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $this->payload('Original'))->assertOk();
        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $this->payload('Replacement'))->assertOk();

        $target = PortfolioRevision::query()
            ->whereJsonContains('payload->profile->headline->en', 'Original')
            ->firstOrFail();

        $response = $this->actingAs($admin)
            ->postJson($this->adminUrl("/portfolio/revisions/{$target->id}/restore"));

        $response->assertOk();
        $this->assertSame('Original', $response->json('profile.headline.en'));
        $this->assertSame('Original', $this->getJson('/portfolio')->json('profile.headline.en'));
    }

    /** A restore is itself a change, so it can be undone in turn. */
    public function test_restoring_records_a_new_revision(): void
    {
        $profile = $this->seededProfile();
        $admin = $this->admin();

        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $this->payload('Original'))->assertOk();
        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $this->payload('Replacement'))->assertOk();

        $before = $profile->revisions()->count();
        $target = $profile->revisions()->orderBy('id')->first();

        $this->actingAs($admin)
            ->postJson($this->adminUrl("/portfolio/revisions/{$target->id}/restore"))
            ->assertOk();

        $this->assertSame($before + 1, $profile->revisions()->count());
    }

    public function test_restoring_also_brings_back_the_collection_rows(): void
    {
        $this->seededProfile();
        $admin = $this->admin();

        $rich = $this->payload('With metrics');
        $rich['metrics'] = [
            ['value' => 'a', 'label' => ['en' => 'A', 'nl' => 'A'], 'is_visible' => true],
            ['value' => 'b', 'label' => ['en' => 'B', 'nl' => 'B'], 'is_visible' => true],
        ];

        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $rich)->assertOk();

        $target = PortfolioRevision::query()->orderByDesc('id')->firstOrFail();

        $stripped = $this->payload('Without metrics');
        $stripped['metrics'] = [];
        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $stripped)->assertOk();

        $response = $this->actingAs($admin)
            ->postJson($this->adminUrl("/portfolio/revisions/{$target->id}/restore"));

        $response->assertOk();
        $this->assertSame(['a', 'b'], array_column($response->json('metrics'), 'value'));
        $this->assertDatabaseHas('portfolio_metrics', ['value' => 'a', 'sort_order' => 1]);
        $this->assertDatabaseHas('portfolio_metrics', ['value' => 'b', 'sort_order' => 2]);
    }

    // A save is one transaction: a half-written page with a revision claiming
    // it is whole would make the undo history lie.
    public function test_a_failed_save_rolls_the_revision_back_with_the_content(): void
    {
        $this->seededProfile();
        $admin = $this->admin();

        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $this->payload('Before'))->assertOk();

        $revisionsBefore = PortfolioRevision::query()->count();

        // Too long for the column, and the service is called directly, so this
        // fails at the insert — after the profile was updated.
        $broken = $this->payload('After');
        $broken['metrics'] = [['value' => str_repeat('x', 300), 'label' => ['en' => 'A', 'nl' => 'A'], 'is_visible' => true]];

        try {
            app(PortfolioContentService::class)->save($broken, $admin);
            $this->fail('The oversized metric should have failed the insert.');
        } catch (QueryException) {
            // Expected. What matters is what survived it.
        }

        $this->assertSame($revisionsBefore, PortfolioRevision::query()->count());
        $this->assertSame('Before', PortfolioProfile::query()->value('headline')['en']);
    }

    /** Every save writes a row, so the table has to be capped. */
    public function test_history_is_capped_at_twenty_entries(): void
    {
        $profile = $this->seededProfile();
        $admin = $this->admin();

        for ($i = 1; $i <= 25; $i++) {
            $this->actingAs($admin)
                ->putJson($this->adminUrl('/portfolio'), $this->payload("Save {$i}"))
                ->assertOk();
        }

        $this->assertSame(20, $profile->revisions()->count());

        // The survivors are the newest ones.
        $newest = $profile->revisions()->orderByDesc('id')->first();
        $this->assertSame('Save 25', $newest->payload['profile']['headline']['en']);
    }

    public function test_a_revision_from_another_profile_is_not_found(): void
    {
        $this->seededProfile();
        $admin = $this->admin();

        $other = PortfolioProfile::create([
            'slug' => 'someone-else',
            'initials' => 'XX',
            'role' => ['en' => 'Other', 'nl' => 'Ander'],
            'headline' => ['en' => 'H', 'nl' => 'H'],
            'summary' => ['en' => 'S', 'nl' => 'S'],
        ]);

        $foreign = PortfolioRevision::create([
            'portfolio_profile_id' => $other->id,
            'user_id' => $admin->id,
            'payload' => $this->payload('Not this page'),
        ]);

        $this->actingAs($admin)
            ->postJson($this->adminUrl("/portfolio/revisions/{$foreign->id}/restore"))
            ->assertNotFound();
    }

    public function test_a_guest_cannot_read_or_restore_history(): void
    {
        $this->seededProfile();

        $this->get($this->adminUrl('/portfolio/revisions'))->assertRedirect($this->adminUrl('/login'));
        $this->post($this->adminUrl('/portfolio/revisions/1/restore'))->assertRedirect($this->adminUrl('/login'));
    }

    public function test_resetting_to_defaults_is_recorded_in_history(): void
    {
        $profile = $this->seededProfile();
        $admin = $this->admin();

        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $this->payload('Mine'))->assertOk();
        $before = $profile->revisions()->count();

        $this->actingAs($admin)->postJson($this->adminUrl('/portfolio/seed-defaults'))->assertOk();

        $this->assertSame($before + 1, $profile->revisions()->count());
        $this->assertSame($admin->id, $profile->revisions()->orderByDesc('id')->first()->user_id);
    }
}
