<?php

namespace App\Services;

use App\Events\PortfolioSaved;
use App\Models\PortfolioProfile;
use App\Models\PortfolioRevision;
use App\Models\User;
use App\Support\PortfolioFields;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for the public page.
 *
 * save(), seedDefaults() and restore() share one transaction and one write
 * path, so a restored version can never be built differently from a saved
 * one, and all three record history without any of them remembering to.
 * **That property is why this class exists** — anything that splits it apart
 * has to keep it.
 *
 * What it is not: it does not decide the shape of a read (PortfolioPayload),
 * does not manage the undo history (PortfolioHistory), and does not hold the
 * list of what a profile is made of (PortfolioFields).
 */
class PortfolioContentService
{
    public function __construct(
        private PortfolioSeeder $seeder,
        private PortfolioPayload $payload,
        private PortfolioHistory $history,
    ) {}

    /**
     * The profile. There is one — PortfolioSeeder::seed() is the only
     * thing that creates it, and it matches on a fixed slug — so this is
     * "the first row" rather than a choice between rows.
     */
    public function activeProfile(): PortfolioProfile
    {
        // Self-healing rather than a 404. There is normally a profile by the
        // time anyone reaches the workspace — `app:install` creates one, and
        // so does `migrate --seed` — but `migrate` alone leaves none, and an
        // editor that answers "not found" is a worse answer than an editor
        // full of the placeholder content the reset button would give you.
        if (! PortfolioProfile::query()->exists()) {
            $this->seeder->seed();
        }

        return PortfolioProfile::query()
            ->with(array_map(
                fn (string $relation) => fn ($query) => $query->orderBy('sort_order'),
                array_combine(PortfolioFields::RELATIONS, PortfolioFields::RELATIONS),
            ))
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(PortfolioProfile $profile, bool $publicOnly): array
    {
        return $this->payload->forProfile($profile, $publicOnly);
    }

    /**
     * Replace-on-save: the admin always submits complete collection state,
     * so each child relation is deleted and recreated from the payload.
     *
     * One event for all three write paths, with a flag, rather than a
     * separate PortfolioRestored: a restore *is* a save through this method —
     * that is the property the class exists for — so two classes would be two
     * wrappers over the same boolean.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, ?User $author, bool $restored = false): PortfolioProfile
    {
        $profile = DB::transaction(function () use ($data, $author): PortfolioProfile {
            $profile = $this->activeProfile();

            $this->history->recordBaseline($profile);

            $profile->update(Arr::only($data['profile'] ?? [], PortfolioFields::PROFILE));

            foreach (PortfolioFields::RELATIONS as $payloadKey => $relation) {
                $this->replaceOrdered($profile, $relation, $data[$payloadKey] ?? [], PortfolioFields::CHILDREN[$relation]);
            }

            $fresh = $this->activeProfile();
            $this->history->record($fresh, $author);

            return $fresh;
        });

        // After the commit, never inside it: a listener that reads the page
        // must not see a version a rollback is about to undo.
        PortfolioSaved::dispatch($profile, $author, $restored);

        return $profile;
    }

    public function seedDefaults(?User $author): PortfolioProfile
    {
        $profile = DB::transaction(function () use ($author): PortfolioProfile {
            // Soft lookup: on a fresh install there is nothing to take a
            // baseline of yet, and activeProfile() would seed the very
            // content this is about to write.
            if (PortfolioProfile::query()->exists()) {
                $this->history->recordBaseline($this->activeProfile());
            }

            $this->seeder->seed();

            $fresh = $this->activeProfile();
            $this->history->record($fresh, $author);

            return $fresh;
        });

        PortfolioSaved::dispatch($profile, $author);

        return $profile;
    }

    /** Restoring is saving the snapshot again, so a restore is undoable too. */
    public function restore(PortfolioRevision $revision, ?User $author): PortfolioProfile
    {
        return $this->save($revision->payload, $author, restored: true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  list<string>  $keys
     */
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
}
