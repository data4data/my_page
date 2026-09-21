<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use App\Support\DefaultPortfolioContent;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The vocabulary the Projects editor picks from. A project still stores the
 * names it chose, so nothing here can strand an old revision.
 */
class TagTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_guest_cannot_read_or_add_tags(): void
    {
        $this->get($this->adminUrl('/tags'))->assertRedirect($this->adminUrl('/login'));
        $this->postJson($this->adminUrl('/tags'), ['name' => 'Sneaky'])->assertUnauthorized();
    }

    public function test_tags_come_back_alphabetically_as_plain_names(): void
    {
        Tag::create(['name' => 'Vue.js']);
        Tag::create(['name' => 'Laravel']);

        $response = $this->actingAs($this->admin())->getJson($this->adminUrl('/tags'));

        $response->assertOk();
        $this->assertSame(['Laravel', 'Vue.js'], $response->json('tags'));
    }

    public function test_a_tag_can_be_added_from_the_editor(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson($this->adminUrl('/tags'), ['name' => 'Livewire']);

        $response->assertCreated();
        $this->assertSame('Livewire', $response->json('tag'));
        $this->assertDatabaseHas('tags', ['name' => 'Livewire']);
    }

    public function test_the_same_word_cannot_join_the_list_twice(): void
    {
        Tag::create(['name' => 'Laravel']);

        $this->actingAs($this->admin())
            ->postJson($this->adminUrl('/tags'), ['name' => 'Laravel'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertSame(1, Tag::query()->where('name', 'Laravel')->count());
    }

    public function test_a_tag_longer_than_the_column_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->postJson($this->adminUrl('/tags'), ['name' => str_repeat('a', 61)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** Safe against a live database, like every other seeder here. */
    public function test_the_seeder_can_run_twice(): void
    {
        $this->seed(TagSeeder::class);
        $first = Tag::count();

        $this->seed(TagSeeder::class);

        $this->assertGreaterThan(0, $first);
        $this->assertSame($first, Tag::count());
    }

    /** Every word the seeded projects are tagged with is offered by the list. */
    public function test_the_seeded_projects_tags_are_all_in_the_vocabulary(): void
    {
        $this->seed(TagSeeder::class);

        $used = collect((new DefaultPortfolioContent)->content()['projects'])
            ->flatMap(fn (array $project) => $project['tags'] ?? [])
            ->unique();

        $offered = Tag::query()->pluck('name');

        foreach ($used as $tag) {
            $this->assertTrue($offered->contains($tag), "Seeded projects use the tag [{$tag}], which TagSeeder does not create.");
        }
    }
}
