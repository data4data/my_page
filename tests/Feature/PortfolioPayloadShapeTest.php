<?php

namespace Tests\Feature;

use App\Models\PortfolioRevision;
use App\Models\User;
use App\Services\PortfolioContentService;
use App\Support\DefaultPortfolioContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What the portfolio endpoints are allowed to say.
 *
 * `GET /portfolio` needs no login. It used to return the models themselves,
 * so `id`, `slug`, `type`, `is_active` and the timestamps were public, and
 * any column added later would have joined them without anyone deciding to
 * publish it. The two resources in app/Http/Resources fix that; these tests
 * are what keeps it fixed.
 */
class PortfolioPayloadShapeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Row metadata, as opposed to page content. None of it is a secret on its
     * own; publishing it by default is the problem, because that default is
     * what quietly publishes the next column too.
     *
     * Hardcoded rather than derived: this list is the claim being made, and a
     * list derived from the same constants the code uses would agree with the
     * code however wrong both were.
     *
     * @var list<string>
     */
    private const METADATA_KEYS = [
        'id',
        'slug',
        'type',
        'is_active',
        'portfolio_profile_id',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    private function seeded(): void
    {
        app(DefaultPortfolioContent::class)->seed();
    }

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /** @return array<string, array{0: bool}> */
    public static function bothEndpoints(): array
    {
        return [
            'public' => [false],
            'admin' => [true],
        ];
    }

    /** @return array<string, mixed> */
    private function fetch(bool $asAdmin): array
    {
        $this->seeded();

        if (! $asAdmin) {
            return $this->getJson('/portfolio')->assertOk()->json();
        }

        return $this->actingAs($this->admin())
            ->getJson($this->adminUrl('/portfolio'))
            ->assertOk()
            ->json();
    }

    #[DataProvider('bothEndpoints')]
    public function test_the_payload_has_exactly_the_five_documented_sections(bool $asAdmin): void
    {
        $this->assertSame(
            ['profile', 'metrics', 'expertise_items', 'projects', 'process_steps'],
            array_keys($this->fetch($asAdmin)),
        );
    }

    /**
     * Keyed to PROFILE_KEYS rather than to a list of its own, on purpose: a
     * field is published because it was made editable, and adding one should
     * not mean editing this test.
     */
    #[DataProvider('bothEndpoints')]
    public function test_the_profile_carries_the_editable_fields_and_nothing_else(bool $asAdmin): void
    {
        $profile = $this->fetch($asAdmin)['profile'];

        $this->assertSame(PortfolioContentService::PROFILE_KEYS, array_keys($profile));
    }

    #[DataProvider('bothEndpoints')]
    public function test_each_collection_row_carries_the_editable_fields_and_nothing_else(bool $asAdmin): void
    {
        $payload = $this->fetch($asAdmin);

        $relations = [
            'metrics' => 'metrics',
            'expertise_items' => 'expertiseItems',
            'projects' => 'projects',
            'process_steps' => 'processSteps',
        ];

        foreach ($relations as $payloadKey => $relation) {
            $this->assertNotEmpty($payload[$payloadKey], "The seeded content should have {$payloadKey} to check.");

            foreach ($payload[$payloadKey] as $index => $row) {
                $this->assertSame(
                    PortfolioContentService::CHILD_KEYS[$relation],
                    array_keys($row),
                    "{$payloadKey}[{$index}] should carry only its editable fields.",
                );
            }
        }
    }

    /** The claim itself, stated separately from the key lists above. */
    #[DataProvider('bothEndpoints')]
    public function test_no_row_metadata_reaches_either_endpoint(bool $asAdmin): void
    {
        $payload = $this->fetch($asAdmin);

        $rows = array_merge(
            [$payload['profile']],
            $payload['metrics'],
            $payload['expertise_items'],
            $payload['projects'],
            $payload['process_steps'],
        );

        foreach ($rows as $row) {
            foreach (self::METADATA_KEYS as $key) {
                $this->assertArrayNotHasKey($key, $row);
            }
        }
    }

    /**
     * The public page still has to be buildable from what it is sent — the
     * fields every section reads, and both social-link placements, which the
     * page picks between per place.
     */
    public function test_the_public_payload_still_carries_what_the_page_draws(): void
    {
        $payload = $this->fetch(asAdmin: false);

        foreach (['initials', 'headline', 'summary', 'default_language', 'show_language_toggle', 'social_links'] as $key) {
            $this->assertArrayHasKey($key, $payload['profile']);
        }

        $this->assertNotEmpty($payload['profile']['social_links']);
        $this->assertArrayHasKey('in_rail', $payload['profile']['social_links'][0]);
        $this->assertArrayHasKey('in_footer', $payload['profile']['social_links'][0]);
    }

    /**
     * A revision is a snapshot of the same payload, so it is trimmed too —
     * which is why restoring an old one cannot put a stale id back on a row.
     */
    public function test_a_revision_snapshot_is_stored_in_the_same_shape(): void
    {
        $this->seeded();
        $admin = $this->admin();

        $fetched = $this->actingAs($admin)->getJson($this->adminUrl('/portfolio'))->json();
        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $fetched)->assertOk();

        $snapshot = PortfolioRevision::query()->orderByDesc('id')->firstOrFail()->payload;

        // Sorted: MySQL's JSON type stores an object as a set of keys and
        // hands them back in its own order, so only the membership is a
        // contract here. The HTTP responses above keep the order they are
        // built in, which is why those compare it.
        $this->assertSame(
            $this->sorted(PortfolioContentService::PROFILE_KEYS),
            $this->sorted(array_keys($snapshot['profile'])),
        );
        $this->assertSame(
            $this->sorted(PortfolioContentService::CHILD_KEYS['metrics']),
            $this->sorted(array_keys($snapshot['metrics'][0])),
        );
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function sorted(array $keys): array
    {
        sort($keys);

        return $keys;
    }
}
