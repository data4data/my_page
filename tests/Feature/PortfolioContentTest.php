<?php

namespace Tests\Feature;

use App\Models\PortfolioProfile;
use App\Models\User;
use App\Support\DefaultPortfolioContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The portfolio save path had no test at all, despite being the most
 * destructive code in the app: it deletes every child row and recreates them
 * from the submitted payload on each save.
 */
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
        return app(DefaultPortfolioContent::class)->seed();
    }

    /**
     * A minimal payload the editor could plausibly submit.
     *
     * Overrides replace outright rather than merging: array_replace_recursive
     * would merge collections index-by-index, so overriding a two-row list
     * with one row would silently leave the second row in place.
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
        ];

        // Profile merges one level deep so a test can change a single field;
        // the collections are replaced whole.
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

    /**
     * PublicPage.vue binds the CTA URLs straight into :href, so a scheme the
     * browser executes is stored XSS against every visitor to the public page.
     */
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
                'profile' => ['social_links' => [['label' => 'Evil', 'url' => $url, 'icon' => 'link']]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['profile.social_links.0.url']);
    }

    /**
     * The rule must stay looser than Laravel's `url`, which would reject the
     * app's own seeded content: both default CTAs are page fragments.
     */
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

    /**
     * The delete-and-recreate behaviour, which nothing covered before: a
     * removed row must actually disappear, and the survivors must be
     * renumbered from 1 rather than keeping their old positions.
     */
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
        $this->assertSame(1, $metrics[0]['sort_order']);
        $this->assertDatabaseMissing('portfolio_metrics', ['value' => '5+']);
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
        $this->assertSame([1, 2, 3], array_column($response->json('metrics'), 'sort_order'));
        $this->assertSame(['first', 'second', 'third'], array_column($response->json('metrics'), 'value'));
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

    /**
     * The editor lets free text be cleared, so this must keep working. Note
     * that ConvertEmptyStringsToNull rewrites '' to null on the way in, which
     * is why the translated rules are 'nullable' rather than 'string'.
     */
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

    /**
     * portfolio_metrics.value is NOT NULL, and an emptied field arrives as
     * null. Before this validation existed that null reached the insert and
     * produced a 500; now it is a readable 422.
     */
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

    /**
     * The real risk of tightening validation is rejecting a payload the editor
     * legitimately produces. The admin UI saves by sending back exactly what it
     * fetched, so fetching the seeded content and PUTting it straight back is
     * the same round trip pressing Save performs.
     */
    public function test_the_seeded_content_can_be_saved_back_unchanged(): void
    {
        $this->seededProfile();
        $admin = $this->admin();

        $fetched = $this->actingAs($admin)->getJson($this->adminUrl('/portfolio'))->json();

        $this->actingAs($admin)
            ->putJson($this->adminUrl('/portfolio'), $fetched)
            ->assertOk();
    }

    /**
     * Same round trip, but through normalizePortfolio() in
     * resources/js/shared/portfolio.js, which fills every translated field to
     * an {en, nl} pair — turning nulls into empty strings before they are sent.
     */
    public function test_the_frontend_normalised_payload_is_accepted(): void
    {
        $this->seededProfile();
        $admin = $this->admin();

        $fetched = $this->actingAs($admin)->getJson($this->adminUrl('/portfolio'))->json();

        $translatable = [
            'role', 'headline', 'summary', 'primary_cta_label', 'secondary_cta_label',
            'location_note', 'availability_note', 'quote', 'quote_author',
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

    // The accented headline words used to be a regex in PublicPage.vue that
    // matched one person's copy, so editing the headline lost the accent.
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

        // Both spellings live in the one list, since only the words in the
        // headline actually on screen can match.
        $this->assertContains('precision', $terms);
        $this->assertContains('precisie', $terms);
    }
}
