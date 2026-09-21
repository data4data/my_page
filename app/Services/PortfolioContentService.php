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
 * The single write path for the public page: save(), seedDefaults() and
 * restore() share one transaction, so a restored version cannot be built
 * differently from a saved one and all three record history.
 */
class PortfolioContentService
{
    public function __construct(
        private PortfolioSeeder $seeder,
        private PortfolioPayload $payload,
        private PortfolioHistory $history,
    ) {}

    /** There is exactly one profile row, so this is the first row, not a choice. */
    public function activeProfile(): PortfolioProfile
    {
        // `migrate` without --seed leaves no profile; seed rather than 404,
        // so the editor opens on placeholder content.
        if (! PortfolioProfile::query()->exists()) {
            $this->seeder->seed();
        }

        return PortfolioProfile::query()
            ->with(PortfolioProfile::orderedChildren())
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
     * Replace-on-save: the admin always submits complete collection state, so
     * each child relation is deleted and recreated from the payload — which is
     * why UpdatePortfolioRequest marks all five `present`.
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
                $profile->replaceChildren($relation, $data[$payloadKey] ?? []);
            }

            // Reload rather than re-query: the rows the eager-loaded relations
            // hold were just deleted, and the snapshot below must be of what
            // was written.
            $profile->load(PortfolioProfile::orderedChildren());
            $this->history->record($profile, $author);

            return $profile;
        });

        // After the commit: a listener must not read a version a rollback undoes.
        PortfolioSaved::dispatch($profile, $author, $restored);

        return $profile;
    }

    public function seedDefaults(?User $author): PortfolioProfile
    {
        $profile = DB::transaction(function () use ($author): PortfolioProfile {
            // Soft lookup: activeProfile() would seed the very content this
            // is about to write.
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
}
