<?php

namespace Tests\Feature;

use App\Models\PortfolioProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Each language is its own URL: the default keeps the bare path, the other gets
 * a prefix, and hreflang ties them together.
 */
class PublicPageLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function profile(array $overrides = []): PortfolioProfile
    {
        return PortfolioProfile::create(array_merge([
            'slug' => 'visit-card',
            'is_active' => true,
            'initials' => 'AB',
            'role' => ['en' => 'Full-Stack Developer', 'nl' => 'Full-stack ontwikkelaar'],
            'headline' => ['en' => 'Headline', 'nl' => 'Kop'],
            'summary' => ['en' => 'I build systems.', 'nl' => 'Ik bouw systemen.'],
            'default_language' => 'en',
        ], $overrides));
    }

    public function test_the_prefixed_language_is_served_in_that_language(): void
    {
        $this->profile();

        $this->get('/nl')
            ->assertOk()
            ->assertSee('<html lang="nl">', false)
            ->assertSee('<meta name="description" content="Ik bouw systemen.">', false)
            ->assertSee('<meta property="og:locale" content="nl">', false)
            ->assertSee('AB | Full-stack ontwikkelaar', false);
    }

    public function test_the_default_language_keeps_the_bare_path(): void
    {
        $this->profile();

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('<meta name="description" content="I build systems.">', false);
    }

    public function test_the_default_language_is_not_also_reachable_under_its_own_prefix(): void
    {
        $this->profile();

        // Two URLs for one page splits whatever ranking either of them earns.
        $this->get('/en')->assertRedirect('/');
        $this->get('/en/hi-developer')->assertRedirect('/hi-developer');
    }

    public function test_the_redirect_is_permanent(): void
    {
        $this->profile();

        $this->get('/en')->assertStatus(301);
    }

    public function test_every_language_is_listed_as_an_alternate(): void
    {
        $this->profile();

        $this->get('/')
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/').'">', false)
            ->assertSee('<link rel="alternate" hreflang="nl" href="'.url('/nl').'">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/').'">', false);
    }

    public function test_alternates_keep_the_page_they_are_on(): void
    {
        $this->profile();

        // The Dutch alternate of /hi-developer is /nl/hi-developer, not /nl.
        $this->get('/hi-developer')
            ->assertSee('<link rel="alternate" hreflang="nl" href="'.url('/nl/hi-developer').'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/hi-developer').'">', false);
    }

    public function test_the_prefixed_page_points_back_at_the_default_one(): void
    {
        $this->profile();

        $this->get('/nl')
            ->assertSee('<link rel="canonical" href="'.url('/nl').'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/').'">', false);
    }

    public function test_the_bare_path_follows_whichever_language_is_default(): void
    {
        $this->profile(['default_language' => 'nl']);

        // Flipping the default moves which language is unprefixed.
        $this->get('/')->assertOk()->assertSee('<html lang="nl">', false);
        $this->get('/en')->assertOk()->assertSee('<html lang="en">', false);
        $this->get('/nl')->assertRedirect('/');
    }

    public function test_an_unsupported_language_is_not_a_page(): void
    {
        $this->profile();

        $this->get('/de')->assertNotFound();
    }

    public function test_the_workspace_is_not_given_language_alternates(): void
    {
        $this->profile();

        // It is noindex, and alternates would name the prefix twice more.
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get($this->adminUrl('/agenda'))
            ->assertOk()
            ->assertDontSee('hreflang', false);
    }

    public function test_a_dutch_visitor_is_told_what_is_wrong_in_dutch(): void
    {
        $this->profile();

        // The page around the form is Dutch, so the validator must not answer
        // in English.
        $this->postJson('/nl/hi-developer', ['name' => '', 'email' => 'nope', 'message' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'message'])
            ->assertSee('Vul je naam in.')
            ->assertSee('geldig e-mailadres');
    }

    public function test_the_unprefixed_form_still_answers_in_the_default_language(): void
    {
        $this->profile();

        $this->postJson('/hi-developer', ['name' => '', 'email' => '', 'message' => ''])
            ->assertStatus(422)
            ->assertSee('The name field is required.');
    }

    public function test_a_valid_dutch_submission_is_still_stored(): void
    {
        $this->profile();

        // The locale must not change what the endpoint does, only how it talks.
        $this->postJson('/nl/hi-developer', [
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'message' => 'Hallo!',
        ])->assertOk();

        $this->assertDatabaseHas('developer_inquiries', ['email' => 'sam@example.com']);
    }
}
