<?php

namespace Tests\Feature;

use App\Models\PortfolioProfile;
use App\Models\PortfolioRevision;
use App\Models\User;
use App\Services\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortfolioContentTest extends TestCase
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

    /**
     * A minimal payload the editor could plausibly submit. Overrides replace
     * outright: a recursive merge would leave rows behind.
     */
    private function payload(array $overrides = []): array
    {
        $base = [
            'profile' => [
                'initials' => 'AB',
                'role' => ['en' => 'Developer', 'nl' => 'Ontwikkelaar'],
                'headline' => ['en' => 'Headline', 'nl' => 'Kop'],
                'summary' => ['en' => 'Summary', 'nl' => 'Samenvatting'],
                'default_language' => 'en',
                'show_language_toggle' => true,
            ],
            'metrics' => [
                ['value' => '5+', 'label' => ['en' => 'Years', 'nl' => 'Jaar'], 'is_visible' => true],
                ['value' => '12', 'label' => ['en' => 'Projects', 'nl' => 'Projecten'], 'is_visible' => true],
            ],
            'expertise_items' => [],
            'projects' => [],
            'process_steps' => [],
            // Every collection is named, empty or not: saving replaces each one
            // whole, so omitting a key would ask to delete it.
            'social_links' => [],
        ];

        // The profile merges one level deep; collections are replaced whole.
        $profile = array_replace($base['profile'], $overrides['profile'] ?? []);

        return array_replace($base, $overrides, ['profile' => $profile]);
    }

    /** @return array<int, array{0: string}> */
    public static function dangerousUrls(): array
    {
        return [
            ['javascript:alert(1)'],
            ['JaVaScRiPt:alert(1)'],
            ['  javascript:alert(1)'],
            ["java\nscript:alert(1)"],
            ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            ['vbscript:msgbox(1)'],
        ];
    }

    // CTA URLs go straight into :href, so an executable scheme is stored XSS.
    #[DataProvider('dangerousUrls')]
    public function test_a_cta_url_with_an_executable_scheme_is_rejected(string $url): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'profile' => ['primary_cta_url' => $url],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['profile.primary_cta_url']);
    }

    #[DataProvider('dangerousUrls')]
    public function test_a_social_link_with_an_executable_scheme_is_rejected(string $url): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'social_links' => [['label' => 'Evil', 'url' => $url, 'icon' => 'link']],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['social_links.0.url']);
    }

    /** Looser than Laravel's `url`, which rejects the seeded fragment CTAs. */
    public function test_fragments_relative_paths_and_mailto_links_are_still_accepted(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'profile' => [
                    'primary_cta_url' => '#work',
                    'secondary_cta_url' => '/hi-developer',
                    'social_links' => [
                        ['label' => 'Email', 'url' => 'mailto:hello@example.com', 'icon' => 'mail'],
                        ['label' => 'GitHub', 'url' => 'https://github.com/', 'icon' => 'github'],
                    ],
                ],
            ]))
            ->assertOk();
    }

    public function test_a_valid_payload_survives_a_round_trip(): void
    {
        $this->seededProfile();

        $response = $this->actingAs($this->admin())
            ->putJson($this->adminUrl('/portfolio'), $this->payload());

        $response->assertOk();
        $this->assertSame('Headline', $response->json('profile.headline.en'));
        $this->assertSame('Kop', $response->json('profile.headline.nl'));
        $this->assertCount(2, $response->json('metrics'));
    }

    public function test_saving_a_shorter_collection_removes_the_missing_rows_and_resequences(): void
    {
        $this->seededProfile();
        $admin = $this->admin();

        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $this->payload())->assertOk();

        $response = $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $this->payload([
            'metrics' => [
                ['value' => '12', 'label' => ['en' => 'Projects', 'nl' => 'Projecten'], 'is_visible' => true],
            ],
        ]));

        $response->assertOk();
        $metrics = $response->json('metrics');
        $this->assertCount(1, $metrics);
        $this->assertSame('12', $metrics[0]['value']);
        $this->assertDatabaseMissing('portfolio_metrics', ['value' => '5+']);
        // sort_order is not published, so renumbering is checked in the database.
        $this->assertDatabaseHas('portfolio_metrics', ['value' => '12', 'sort_order' => 1]);
    }

    public function test_sort_order_follows_the_submitted_position(): void
    {
        $this->seededProfile();

        $response = $this->actingAs($this->admin())->putJson($this->adminUrl('/portfolio'), $this->payload([
            'metrics' => [
                ['value' => 'first', 'label' => ['en' => 'A', 'nl' => 'A'], 'is_visible' => true],
                ['value' => 'second', 'label' => ['en' => 'B', 'nl' => 'B'], 'is_visible' => true],
                ['value' => 'third', 'label' => ['en' => 'C', 'nl' => 'C'], 'is_visible' => true],
            ],
        ]));

        $response->assertOk();
        $this->assertSame(['first', 'second', 'third'], array_column($response->json('metrics'), 'value'));

        foreach (['first' => 1, 'second' => 2, 'third' => 3] as $value => $position) {
            $this->assertDatabaseHas('portfolio_metrics', ['value' => $value, 'sort_order' => $position]);
        }
    }

    public function test_an_unknown_default_language_is_rejected(): void
    {
        $this->seededProfile();

        $this->actingAs($this->admin())
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'profile' => ['default_language' => 'klingon'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['profile.default_language']);
    }

    public function test_a_translated_field_sent_as_a_plain_string_is_rejected(): void
    {
        $this->seededProfile();

        $this->actingAs($this->admin())
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'profile' => ['headline' => 'not a translation pair'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['profile.headline']);
    }

    public function test_a_metric_over_the_column_length_is_rejected(): void
    {
        $this->seededProfile();

        $this->actingAs($this->admin())
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'metrics' => [
                    ['value' => str_repeat('x', 25), 'label' => ['en' => 'A', 'nl' => 'A'], 'is_visible' => true],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['metrics.0.value']);
    }

    // ConvertEmptyStringsToNull rewrites '' to null, hence 'nullable' rules.
    public function test_cleared_free_text_is_accepted(): void
    {
        $this->seededProfile();

        $this->actingAs($this->admin())
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'profile' => ['quote' => ['en' => '', 'nl' => '']],
                'metrics' => [
                    ['value' => '5+', 'label' => ['en' => '', 'nl' => ''], 'is_visible' => true],
                ],
            ]))
            ->assertOk();
    }

    // The column is NOT NULL, so a cleared value must 422 rather than 500.
    public function test_a_cleared_metric_value_is_rejected_rather_than_hitting_the_database(): void
    {
        $this->seededProfile();

        $this->actingAs($this->admin())
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'metrics' => [
                    ['value' => '', 'label' => ['en' => 'A', 'nl' => 'A'], 'is_visible' => true],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['metrics.0.value']);
    }

    // The same round trip pressing Save performs.
    public function test_the_seeded_content_can_be_saved_back_unchanged(): void
    {
        $this->seededProfile();
        $admin = $this->admin();

        $fetched = $this->actingAs($admin)->getJson($this->adminUrl('/portfolio'))->json();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $fetched)
            ->assertOk();
    }

    // The same again, but shaped the way normalizePortfolio() sends it.
    public function test_the_frontend_normalised_payload_is_accepted(): void
    {
        $this->seededProfile();
        $admin = $this->admin();

        $fetched = $this->actingAs($admin)->getJson($this->adminUrl('/portfolio'))->json();

        $translatable = [
            'role', 'headline', 'summary', 'primary_cta_label', 'secondary_cta_label',
            'footer_note_left', 'footer_note_right', 'quote', 'quote_author',
        ];

        foreach ($translatable as $field) {
            $value = $fetched['profile'][$field] ?? null;
            $fetched['profile'][$field] = [
                'en' => is_array($value) ? ($value['en'] ?? '') : '',
                'nl' => is_array($value) ? ($value['nl'] ?? $value['en'] ?? '') : '',
            ];
        }

        $itemFields = [
            'metrics' => ['label'],
            'expertise_items' => ['title', 'description'],
            'projects' => ['title', 'summary', 'result'],
            'process_steps' => ['title', 'description'],
        ];

        foreach ($itemFields as $collection => $fields) {
            foreach ($fetched[$collection] as $index => $item) {
                foreach ($fields as $field) {
                    $value = $item[$field] ?? null;
                    $fetched[$collection][$index][$field] = [
                        'en' => is_array($value) ? ($value['en'] ?? '') : '',
                        'nl' => is_array($value) ? ($value['nl'] ?? $value['en'] ?? '') : '',
                    ];
                }
            }
        }

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $fetched)
            ->assertOk();
    }

    public function test_a_guest_cannot_save_the_portfolio(): void
    {
        $this->seededProfile();

        $this->putJson($this->adminUrl('/portfolio'), $this->payload())->assertUnauthorized();
    }

    public function test_the_public_endpoint_hides_invisible_rows(): void
    {
        $this->seededProfile();

        $this->actingAs($this->admin())->putJson($this->adminUrl('/portfolio'), $this->payload([
            'metrics' => [
                ['value' => 'shown', 'label' => ['en' => 'A', 'nl' => 'A'], 'is_visible' => true],
                ['value' => 'hidden', 'label' => ['en' => 'B', 'nl' => 'B'], 'is_visible' => false],
            ],
        ]))->assertOk();

        $values = array_column($this->getJson('/portfolio')->json('metrics'), 'value');

        $this->assertSame(['shown'], $values);
    }

    // These used to be hardcoded, so editing the headline lost the accent.
    public function test_headline_highlights_round_trip_through_a_save(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $highlights = [
            ['text' => 'clarity', 'tone' => 'blue'],
            ['text' => 'results', 'tone' => 'gold'],
        ];

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'profile' => ['headline_highlights' => $highlights],
            ]))
            ->assertOk()
            ->assertJsonPath('profile.headline_highlights', $highlights);

        // And they reach the public payload, which is where they are used.
        $this->getJson('/portfolio')
            ->assertOk()
            ->assertJsonPath('profile.headline_highlights', $highlights);
    }

    public function test_a_highlight_tone_outside_the_two_css_classes_is_rejected(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'profile' => ['headline_highlights' => [['text' => 'clarity', 'tone' => 'chartreuse']]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['profile.headline_highlights.0.tone']);
    }

    public function test_the_seeded_defaults_carry_highlights_for_both_languages(): void
    {
        $profile = $this->seededProfile();

        $terms = array_column($profile->headline_highlights, 'text');

        // Both spellings in one list; only those on screen can match.
        $this->assertContains('precision', $terms);
        $this->assertContains('precisie', $terms);
    }

    public function test_a_hidden_social_link_is_kept_out_of_the_public_payload(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'social_links' => [
                    ['label' => 'Shown', 'url' => 'https://example.test/shown', 'icon' => 'link', 'in_rail' => true, 'in_footer' => true],
                    ['label' => 'Hidden', 'url' => 'https://example.test/hidden', 'icon' => 'link', 'in_rail' => false, 'in_footer' => false],
                ],
            ]))
            ->assertOk();

        $public = $this->getJson('/portfolio')->assertOk()->json('social_links');

        $this->assertCount(1, $public);
        $this->assertSame('Shown', $public[0]['label']);

        // The editor sees both, or a hidden one could never come back.
        $admin_links = $this->actingAs($admin)->getJson($this->adminUrl('/portfolio'))->json('social_links');
        $this->assertCount(2, $admin_links);
    }

    public function test_switching_every_link_off_leaves_the_public_payload_with_none(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'social_links' => [
                    ['label' => 'One', 'url' => 'https://example.test/one', 'icon' => 'link', 'in_rail' => false, 'in_footer' => false],
                ],
            ]))
            ->assertOk();

        $this->assertSame([], $this->getJson('/portfolio')->assertOk()->json('social_links'));
    }

    // Filtering must not touch the caller's instance: the same method builds
    // revision snapshots, which keep everything.
    public function test_filtering_does_not_strip_hidden_links_from_a_saved_revision(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'social_links' => [
                    ['label' => 'Hidden', 'url' => 'https://example.test/hidden', 'icon' => 'link', 'in_rail' => false, 'in_footer' => false],
                ],
            ]))
            ->assertOk();

        // Read the public payload first: if it mutated shared state, the
        // snapshot would have lost the link.
        $this->getJson('/portfolio')->assertOk();

        $snapshot = PortfolioRevision::query()->latest('id')->first()->payload;

        $this->assertCount(1, $snapshot['social_links']);
    }

    /** A link with no flag at all must still show. */
    public function test_a_link_saved_without_a_visibility_flag_still_shows(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'social_links' => [
                    ['label' => 'Legacy', 'url' => 'https://example.test/legacy', 'icon' => 'link'],
                ],
            ]))
            ->assertOk();

        $public = $this->getJson('/portfolio')->assertOk()->json('social_links');

        $this->assertCount(1, $public);
        $this->assertSame('Legacy', $public[0]['label']);
    }

    public function test_a_link_can_appear_in_one_place_and_not_the_other(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'social_links' => [
                    ['label' => 'Rail only', 'url' => 'https://example.test/rail', 'icon' => 'link', 'in_rail' => true, 'in_footer' => false],
                    ['label' => 'Footer only', 'url' => 'https://example.test/footer', 'icon' => 'link', 'in_rail' => false, 'in_footer' => true],
                    ['label' => 'Neither', 'url' => 'https://example.test/none', 'icon' => 'link', 'in_rail' => false, 'in_footer' => false],
                ],
            ]))
            ->assertOk();

        $public = $this->getJson('/portfolio')->assertOk()->json('social_links');

        // The one shown nowhere is dropped; the placements survive.
        $this->assertSame(['Rail only', 'Footer only'], array_column($public, 'label'));
        $this->assertTrue($public[0]['in_rail']);
        $this->assertFalse($public[0]['in_footer']);
        $this->assertFalse($public[1]['in_rail']);
        $this->assertTrue($public[1]['in_footer']);
    }

    // A row shown in neither place is not published: is_visible is generated
    // from the two placements.
    public function test_a_link_shown_in_neither_place_is_not_published(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'social_links' => [
                    ['label' => 'Rail only', 'url' => 'https://example.test/rail', 'icon' => 'link', 'in_rail' => true, 'in_footer' => false],
                    ['label' => 'Footer only', 'url' => 'https://example.test/footer', 'icon' => 'link', 'in_rail' => false, 'in_footer' => true],
                    ['label' => 'Nowhere', 'url' => 'https://example.test/none', 'icon' => 'link', 'in_rail' => false, 'in_footer' => false],
                ],
            ]))
            ->assertOk();

        $public = $this->getJson('/portfolio')->assertOk()->json('social_links');

        $this->assertSame(['Rail only', 'Footer only'], array_column($public, 'label'));
    }

    public function test_a_contact_address_that_is_not_an_email_is_rejected(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'profile' => ['contact_email' => 'not-an-address'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['profile.contact_email']);
    }

    public function test_the_contact_address_may_be_cleared(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'profile' => ['contact_email' => null],
            ]))
            ->assertOk();

        // The button that reads this hides itself rather than mailing nowhere.
        $this->assertNull(PortfolioProfile::query()->first()->contact_email);
    }

    // Fetched by a crawler on another host, so not a fragment or a mailto.
    #[DataProvider('unusablePreviewImages')]
    public function test_a_preview_image_that_no_crawler_could_fetch_is_rejected(string $url): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'profile' => ['social_image_url' => $url],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['profile.social_image_url']);
    }

    public static function unusablePreviewImages(): array
    {
        return [
            'fragment' => ['#work'],
            'relative path' => ['/images/card.png'],
            'mailto' => ['mailto:someone@example.com'],
            'script' => ['javascript:alert(1)'],
        ];
    }

    public function test_an_absolute_preview_image_is_accepted(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload([
                'profile' => ['social_image_url' => 'https://cdn.example.com/card.png'],
            ]))
            ->assertOk();

        $this->assertSame(
            'https://cdn.example.com/card.png',
            PortfolioProfile::query()->first()->social_image_url,
        );
    }

    /**
     * Saving replaces each collection whole, so a payload that merely forgets
     * one used to delete every row in it without saying so.
     *
     * @return array<int, array{0: string}>
     */
    public static function childCollections(): array
    {
        return [['metrics'], ['expertise_items'], ['projects'], ['process_steps'], ['social_links']];
    }

    #[DataProvider('childCollections')]
    public function test_omitting_a_collection_is_rejected_rather_than_emptying_it(string $collection): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $payload = $this->payload();
        unset($payload[$collection]);

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$collection]);
    }

    // Having none of something is still allowed — only forgetting to say so is not.
    public function test_an_empty_collection_is_accepted(): void
    {
        $admin = $this->admin();
        $this->seededProfile();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $this->payload(['metrics' => []]))
            ->assertOk();

        $this->assertSame(0, PortfolioProfile::query()->firstOrFail()->metrics()->count());
    }
}
