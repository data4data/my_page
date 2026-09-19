<?php

namespace App\Services;

use App\Http\Resources\PortfolioItemResource;
use App\Http\Resources\PortfolioProfileResource;
use App\Models\PortfolioProfile;
use App\Models\PortfolioRevision;
use App\Models\User;
use App\Support\DefaultPortfolioContent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Every write to the public page goes through here. save(), restore() and
 * seedDefaults() share one transaction and one write path, so a restored
 * version is built the same way a saved one is, and all three record history.
 */
class PortfolioContentService
{
    // Every save writes a row, so the table needs a cap.
    private const KEEP_REVISIONS = 20;

    /**
     * What a profile is made of, and the only thing any endpoint publishes —
     * PortfolioProfileResource is built from this list. Public because the
     * resource reads it; still the single source save() writes by.
     *
     * @var list<string>
     */
    public const PROFILE_KEYS = [
        'initials',
        'role',
        'headline',
        'headline_highlights',
        'summary',
        'primary_cta_label',
        'primary_cta_url',
        'secondary_cta_label',
        'secondary_cta_url',
        'contact_email',
        'social_image_url',
        'location_note',
        'availability_note',
        'quote',
        'quote_author',
        'default_language',
        'show_language_toggle',
    ];

    /**
     * The same, per child relation. PortfolioItemResource reads it.
     *
     * @var array<string, list<string>>
     */
    public const CHILD_KEYS = [
        'metrics' => ['value', 'label', 'is_visible'],
        'expertiseItems' => ['title', 'description', 'icon', 'category', 'is_visible'],
        'projects' => ['title', 'summary', 'result', 'tags', 'visual_style', 'is_visible'],
        'processSteps' => ['group', 'title', 'description', 'icon', 'is_visible'],
        // No is_visible: it is generated from the two placements, so it is
        // neither written nor published. payload() still filters on it, which
        // is how a link shown in neither place stays off the public page.
        'socialLinks' => ['label', 'url', 'icon', 'in_rail', 'in_footer'],
    ];

    // Payload keys (snake_case) -> relation names on PortfolioProfile.
    private const PAYLOAD_TO_RELATION = [
        'metrics' => 'metrics',
        'expertise_items' => 'expertiseItems',
        'projects' => 'projects',
        'process_steps' => 'processSteps',
        'social_links' => 'socialLinks',
    ];

    public function __construct(private DefaultPortfolioContent $defaults) {}

    /**
     * The profile. There is one — DefaultPortfolioContent::seed() is the only
     * thing that creates it, and it matches on a fixed slug — so this is
     * "the first row" rather than a choice between rows.
     */
    public function activeProfile(): PortfolioProfile
    {
        return PortfolioProfile::query()
            ->with([
                'metrics' => fn ($query) => $query->orderBy('sort_order'),
                'expertiseItems' => fn ($query) => $query->orderBy('sort_order'),
                'socialLinks' => fn ($query) => $query->orderBy('sort_order'),
                'projects' => fn ($query) => $query->orderBy('sort_order'),
                'processSteps' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->firstOrFail();
    }

    /**
     * The shape every read of the page returns — the public endpoint, the
     * admin endpoint and the revision snapshot alike.
     *
     * It is assembled by the two resources rather than by handing the models
     * out, so the keys it carries are exactly PROFILE_KEYS and CHILD_KEYS and
     * nothing that happens to sit in the same table.
     *
     * @return array<string, mixed>
     */
    public function payload(PortfolioProfile $profile, bool $publicOnly): array
    {
        $visible = fn ($items) => $publicOnly ? $items->where('is_visible', true)->values() : $items->values();

        $payload = ['profile' => PortfolioProfileResource::make($profile)->resolve()];

        foreach (self::PAYLOAD_TO_RELATION as $payloadKey => $relation) {
            $payload[$payloadKey] = PortfolioItemResource::forRelation($visible($profile->{$relation}), $relation);
        }

        return $payload;
    }

    /**
     * Replace-on-save: the admin always submits complete collection state,
     * so each child relation is deleted and recreated from the payload.
     */
    public function save(array $data, ?User $author): PortfolioProfile
    {
        return DB::transaction(function () use ($data, $author): PortfolioProfile {
            $profile = $this->activeProfile();

            $this->recordBaseline($profile);

            $profile->update(Arr::only($data['profile'] ?? [], self::PROFILE_KEYS));

            foreach (self::PAYLOAD_TO_RELATION as $payloadKey => $relation) {
                $this->replaceOrdered($profile, $relation, $data[$payloadKey] ?? [], self::CHILD_KEYS[$relation]);
            }

            $fresh = $this->activeProfile();
            $this->recordRevision($fresh, $author);

            return $fresh;
        });
    }

    public function seedDefaults(?User $author): PortfolioProfile
    {
        return DB::transaction(function () use ($author): PortfolioProfile {
            // Soft lookup: on a fresh install there is nothing to take a
            // baseline of yet, and activeProfile()'s firstOrFail would throw.
            $existing = PortfolioProfile::query()->first();

            if ($existing) {
                $this->recordBaseline($this->activeProfile());
            }

            $this->defaults->seed();

            $fresh = $this->activeProfile();
            $this->recordRevision($fresh, $author);

            return $fresh;
        });
    }

    /** Restoring is saving the snapshot again, so a restore is undoable too. */
    public function restore(PortfolioRevision $revision, ?User $author): PortfolioProfile
    {
        return $this->save($revision->payload, $author);
    }

    private function replaceOrdered(PortfolioProfile $profile, string $relation, array $items, array $keys): void
    {
        $profile->{$relation}()->delete();

        foreach (array_values($items) as $index => $item) {
            $payload = Arr::only($item, $keys);
            $payload['sort_order'] = $index + 1;

            // Only where the relation actually has the column. Social links
            // derive theirs from the two placements, and MySQL rejects an
            // INSERT that names a generated column.
            if (in_array('is_visible', $keys, true)) {
                $payload['is_visible'] = $item['is_visible'] ?? true;
            }

            $profile->{$relation}()->create($payload);
        }
    }

    /**
     * Snapshots the starting state so the very first save can be undone.
     * No author, because nobody made this state.
     */
    private function recordBaseline(PortfolioProfile $profile): void
    {
        if ($profile->revisions()->exists()) {
            return;
        }

        $this->recordRevision($profile, null);
    }

    private function recordRevision(PortfolioProfile $profile, ?User $author): void
    {
        PortfolioRevision::create([
            'portfolio_profile_id' => $profile->id,
            'user_id' => $author?->id,
            'payload' => $this->payload($profile, publicOnly: false),
        ]);

        $this->pruneRevisions($profile);
    }

    private function pruneRevisions(PortfolioProfile $profile): void
    {
        // Select the ones to keep, then delete the rest. An OFFSET with no
        // LIMIT is a MySQL syntax error, so the newest-N cannot be skipped.
        // By id, not created_at: several saves can share a second.
        $keep = PortfolioRevision::query()
            ->where('portfolio_profile_id', $profile->id)
            ->orderByDesc('id')
            ->limit(self::KEEP_REVISIONS)
            ->pluck('id');

        PortfolioRevision::query()
            ->where('portfolio_profile_id', $profile->id)
            ->whereNotIn('id', $keep)
            ->delete();
    }
}
