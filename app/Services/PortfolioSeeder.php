<?php

namespace App\Services;

use App\Contracts\PortfolioSeedContent;
use App\Models\PortfolioProfile;
use App\Support\PortfolioFields;
use Illuminate\Support\Facades\DB;

/**
 * Writes the starting content into the database. Idempotent: it matches the
 * profile on a fixed slug and replaces each child collection whole.
 */
class PortfolioSeeder
{
    /** The only thing that identifies a profile. Generic, since this is a fork. */
    public const SLUG = 'default';

    public function __construct(private PortfolioSeedContent $content) {}

    public function seed(): PortfolioProfile
    {
        return DB::transaction(function (): PortfolioProfile {
            $content = $this->content->content();

            $profile = PortfolioProfile::updateOrCreate(
                ['slug' => self::SLUG],
                $content['profile'],
            );

            foreach (PortfolioFields::RELATIONS as $payloadKey => $relation) {
                // The same writer a save uses, so seeded content and saved
                // content cannot be built differently.
                $profile->replaceChildren($relation, $content[$payloadKey] ?? []);
            }

            return $profile;
        });
    }
}
