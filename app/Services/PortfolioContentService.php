<?php

namespace App\Services;

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

    private const PROFILE_KEYS = [
        'initials',
        'role',
        'headline',
        'headline_highlights',
        'summary',
        'primary_cta_label',
        'primary_cta_url',
        'secondary_cta_label',
        'secondary_cta_url',
        'location_note',
        'availability_note',
        'quote',
        'quote_author',
        'social_links',
        'default_language',
        'show_language_toggle',
    ];

    private const CHILD_KEYS = [
        'metrics' => ['value', 'label', 'is_visible'],
        'expertiseItems' => ['title', 'description', 'icon', 'category', 'is_visible'],
        'projects' => ['title', 'summary', 'result', 'tags', 'visual_style', 'is_visible'],
        'processSteps' => ['group', 'title', 'description', 'icon', 'is_visible'],
    ];

    // Payload keys (snake_case) -> relation names on PortfolioProfile.
    private const PAYLOAD_TO_RELATION = [
        'metrics' => 'metrics',
        'expertise_items' => 'expertiseItems',
        'projects' => 'projects',
        'process_steps' => 'processSteps',
    ];

    public function __construct(private DefaultPortfolioContent $defaults) {}

    public function activeProfile(): PortfolioProfile
    {
        return PortfolioProfile::query()
            ->where('is_active', true)
            ->with([
                'metrics' => fn ($query) => $query->orderBy('sort_order'),
                'expertiseItems' => fn ($query) => $query->orderBy('sort_order'),
                'projects' => fn ($query) => $query->orderBy('sort_order'),
                'processSteps' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->firstOrFail();
    }

    public function payload(PortfolioProfile $profile, bool $publicOnly): array
    {
        $visible = fn ($items) => $publicOnly ? $items->where('is_visible', true)->values() : $items->values();

        return [
            'profile' => $publicOnly ? $this->withVisibleSocialLinks($profile) : $profile,
            'metrics' => $visible($profile->metrics),
            'expertise_items' => $visible($profile->expertiseItems),
            'projects' => $visible($profile->projects),
            'process_steps' => $visible($profile->processSteps),
        ];
    }

    /**
     * Social links are a JSON column, not a child table, so the filtering
     * above misses them. Drops a link only when it shows in neither place;
     * both flags still travel, because the page picks per place.
     *
     * Cloned, not filtered in place: the caller's instance also builds
     * revision snapshots, which must keep every link.
     */
    private function withVisibleSocialLinks(PortfolioProfile $profile): PortfolioProfile
    {
        $copy = clone $profile;

        $copy->social_links = collect($profile->social_links ?? [])
            ->filter(fn (array $link) => self::showsIn($link, 'in_rail') || self::showsIn($link, 'in_footer'))
            ->values()
            ->all();

        return $copy;
    }

    /**
     * Links saved before the two placements existed carry only is_visible, so
     * it stands in for a missing placement. Without that, the rail and footer
     * would empty on every install that already had links.
     *
     * Mirrored by showsIn() in resources/js/shared/portfolio.js — keep in step.
     *
     * @param  array<string, mixed>  $link
     */
    private static function showsIn(array $link, string $placement): bool
    {
        return (bool) ($link[$placement] ?? ($link['is_visible'] ?? true));
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
            $existing = PortfolioProfile::query()->where('is_active', true)->first();

            if ($existing) {
                $this->recordBaseline($this->activeProfile());
            }

            $this->defaults->seed();

            $this->activate($this->activeProfile());

            $fresh = $this->activeProfile();
            $this->recordRevision($fresh, $author);

            return $fresh;
        });
    }

    /**
     * Exactly one profile is live. activeProfile() takes the first is_active
     * row, so a second would not error, it would just decide the public page.
     *
     * Done here, not as a partial unique index: SQLite has those and MySQL
     * does not, and this project behaves the same on both.
     */
    public function activate(PortfolioProfile $profile): PortfolioProfile
    {
        return DB::transaction(function () use ($profile): PortfolioProfile {
            PortfolioProfile::query()
                ->whereKeyNot($profile->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $profile->update(['is_active' => true]);

            return $profile;
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
            $payload['is_visible'] = $item['is_visible'] ?? true;

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
        // By id, not created_at: several saves can share a second.
        $stale = PortfolioRevision::query()
            ->where('portfolio_profile_id', $profile->id)
            ->orderByDesc('id')
            ->pluck('id')
            ->slice(self::KEEP_REVISIONS);

        if ($stale->isNotEmpty()) {
            PortfolioRevision::query()->whereIn('id', $stale)->delete();
        }
    }
}
