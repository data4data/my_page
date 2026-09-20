<?php

namespace Tests\Feature;

use App\Models\PortfolioRevision;
use App\Models\User;
use App\Services\PortfolioSeeder;
use App\Support\PortfolioFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What the portfolio endpoints are allowed to say. `GET /portfolio` needs no
 * login, so nothing may reach it that was not deliberately published.
 */
class PortfolioPayloadShapeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Row metadata, not page content. Hardcoded rather than derived: this list
     * is the claim, and a derived one would agree with the code however wrong.
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
        app(PortfolioSeeder::class)->seed();
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
    public function test_the_payload_has_exactly_the_documented_sections(bool $asAdmin): void
    {
        $this->assertSame(
            ['profile', 'metrics', 'expertise_items', 'projects', 'process_steps', 'social_links'],
            array_keys($this->fetch($asAdmin)),
        );
    }

    // Keyed to the constant, so adding a field needs no test edit.
    #[DataProvider('bothEndpoints')]
    public function test_the_profile_carries_the_editable_fields_and_nothing_else(bool $asAdmin): void
    {
        $profile = $this->fetch($asAdmin)['profile'];

        $this->assertSame(PortfolioFields::PROFILE, array_keys($profile));
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
            'social_links' => 'socialLinks',
        ];

        foreach ($relations as $payloadKey => $relation) {
            $this->assertNotEmpty($payload[$payloadKey], "The seeded content should have {$payloadKey} to check.");

            foreach ($payload[$payloadKey] as $index => $row) {
                $this->assertSame(
                    PortfolioFields::CHILDREN[$relation],
                    array_keys($row),
                    "{$payloadKey}[{$index}] should carry only its editable fields.",
                );
            }
        }
    }

    #[DataProvider('bothEndpoints')]
    public function test_no_row_metadata_reaches_either_endpoint(bool $asAdmin): void
    {
        $payload = $this->fetch($asAdmin);

        $rows = array_merge(
            [$payload['profile']],
            $payload['social_links'],
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

    public function test_the_public_payload_still_carries_what_the_page_draws(): void
    {
        $payload = $this->fetch(asAdmin: false);

        foreach (['initials', 'headline', 'summary', 'default_language', 'show_language_toggle'] as $key) {
            $this->assertArrayHasKey($key, $payload['profile']);
        }

        // Each link carries both placements, so the page can pick per place.
        $this->assertNotEmpty($payload['social_links']);
        $this->assertArrayHasKey('in_rail', $payload['social_links'][0]);
        $this->assertArrayHasKey('in_footer', $payload['social_links'][0]);
    }

    // A snapshot is the same payload, so restoring cannot put a stale id back.
    public function test_a_revision_snapshot_is_stored_in_the_same_shape(): void
    {
        $this->seeded();
        $admin = $this->admin();

        $fetched = $this->actingAs($admin)->getJson($this->adminUrl('/portfolio'))->json();
        $this->actingAs($admin)->putJson($this->adminUrl('/portfolio'), $fetched)->assertOk();

        $snapshot = PortfolioRevision::query()->orderByDesc('id')->firstOrFail()->payload;

        // Sorted: MySQL's JSON type hands keys back in its own order, so only
        // membership is a contract here.
        $this->assertSame(
            $this->sorted(PortfolioFields::PROFILE),
            $this->sorted(array_keys($snapshot['profile'])),
        );
        $this->assertSame(
            $this->sorted(PortfolioFields::CHILDREN['metrics']),
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
