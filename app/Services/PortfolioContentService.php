<?php

namespace App\Services;

use App\Models\PortfolioProfile;
use App\Models\PortfolioRevision;
use App\Models\User;
use App\Support\DefaultPortfolioContent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Every write to the public page goes through here.
 *
 * That single write path is the point: save(), restore() and seedDefaults()
 * all end up in the same transaction, so a restored version can never be
 * built differently from a saved one, and all three are recorded in history
 * without any of them having to remember to do it.
 */
class PortfolioContentService
{
    // Every save writes a row, so without a cap the table grows forever.
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

    // Maps the snake_case payload keys the frontend sends to the camelCase
    // relation names on PortfolioProfile.
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
     * Social links live in a JSON column rather than a child table, so the
     * child-collection filtering above never reaches them — a link switched
     * off in the editor would still have gone out in the public payload, and
     * an "off" switch that publishes the link anyway is not an off switch.
     *
     * A link is dropped only when it appears in neither place. Both flags
     * still travel, because the page decides per place which links to draw.
     *
     * Cloned rather than filtered in place: the caller's instance is used
     * elsewhere, including to build revision snapshots, which must keep
     * everything.
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
     * `is_visible` is the flag the two placements replaced, back when one
     * switch covered both. Links saved then carry only that, so it stands in
     * for a missing placement — reading one as "off" would have emptied the
     * rail and the footer at once on every install that already had links.
     *
     * Mirrored by showsIn() in resources/js/shared/portfolio.js; keep the two
     * in step.
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
     * Exactly one profile is live at a time. activeProfile() takes the first
     * is_active row it finds, so a second one would not error — it would
     * quietly decide which page the public site serves.
     *
     * Enforced here rather than as a partial unique index: SQLite supports
     * those and MySQL does not, and this project keeps behaviour identical on
     * both (task statuses are strings, not DB enums, for the same reason).
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

    /**
     * Restoring is just saving the snapshot again, which is why it needs no
     * logic of its own. It also records a new revision, so a restore can
     * itself be undone.
     */
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
     * Without this the very first save would be irreversible — the moment
     * someone is most likely to want an undo, since they have just found the
     * editor. Recorded with no author: nobody made this state, it is where
     * the page started.
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
        // Ordered by id, not created_at: several saves can land in the same
        // second, and ids never tie.
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
