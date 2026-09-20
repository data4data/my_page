<?php

namespace Tests\Feature;

use App\Models\PortfolioProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Link-preview crawlers run no JavaScript, so everything a card shows has to be
 * in this response — and the workspace, on the same shell, must give nothing.
 */
class PublicPageMetaTest extends TestCase
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
        ], $overrides));
    }

    public function test_the_public_page_describes_itself_in_the_html_itself(): void
    {
        $this->profile();

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="description" content="I build systems.">', false)
            ->assertSee('<meta property="og:title" content="AB | Full-Stack Developer">', false)
            ->assertSee('<meta property="og:description" content="I build systems.">', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false);
    }

    public function test_each_route_is_canonical_to_itself(): void
    {
        $this->profile();

        // Not every route pointing at the root: /hi-developer is its own page.
        $this->get('/hi-developer')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/hi-developer').'">', false);
    }

    public function test_the_document_language_follows_the_profile(): void
    {
        $this->profile(['default_language' => 'nl']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="nl">', false)
            // The description follows it too, or the card and the page disagree.
            ->assertSee('content="Ik bouw systemen."', false);
    }

    public function test_the_structured_data_is_valid_json(): void
    {
        $this->profile();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches));

        $schema = json_decode($matches[1], true);

        $this->assertIsArray($schema, 'the ld+json block should parse');
        $this->assertSame('Person', $schema['@type']);
        $this->assertSame('Full-Stack Developer', $schema['jobTitle']);
    }

    // A field holding markup must not close the block and spill into the page.
    public function test_structured_data_cannot_break_out_of_its_script_block(): void
    {
        $this->profile(['summary' => ['en' => 'Ship it </script><script>alert(1)</script>', 'nl' => '']]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_the_workspace_tells_crawlers_to_stay_out(): void
    {
        $this->profile();

        $response = $this->get($this->adminUrl('/login'))->assertOk();

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        // Private pages carry no description and no card.
        $response->assertDontSee('og:title', false);
        $response->assertDontSee('<link rel="canonical"', false);
    }

    public function test_the_public_page_is_not_marked_noindex(): void
    {
        $this->profile();

        $this->get('/')->assertOk()->assertDontSee('noindex', false);
    }

    /** Every page still has to render before anything is seeded. */
    public function test_a_fresh_install_renders_without_a_profile(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Digital Visit Card</title>', false)
            ->assertDontSee('application/ld+json', false);
    }

    public function test_a_preview_image_is_offered_when_one_is_set(): void
    {
        $this->profile(['social_image_url' => 'https://cdn.example.com/card.png']);

        $this->get('/')
            ->assertSee('<meta property="og:image" content="https://cdn.example.com/card.png">', false)
            ->assertSee('<meta name="twitter:image" content="https://cdn.example.com/card.png">', false)
            // The big card is only worth asking for once there is a picture.
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_without_an_image_the_small_card_is_asked_for(): void
    {
        $this->profile();

        $this->get('/')
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertDontSee('og:image', false);
    }
}
