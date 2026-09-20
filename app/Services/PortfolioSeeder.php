<?php

namespace App\Services;

use App\Contracts\PortfolioSeedContent;
use App\Models\PortfolioProfile;
use App\Support\PortfolioFields;
use Illuminate\Support\Facades\DB;

/**
 * Writes the starting content into the database.
 *
 * Separate from the content itself (PortfolioSeedContent) because only one of
 * the two is worth replacing: a fork wants its own words, not its own way of
 * inserting rows.
 *
 * Idempotent — it matches the profile on a fixed slug and replaces each child
 * collection whole, so running it twice leaves one profile, not two.
 */
class PortfolioSeeder
{
    /**
     * The row this matches on, and the only thing that identifies a profile.
     * Deliberately not anybody's initials: this project is meant to be
     * forked, and the seeded slug used to be the author's.
     */
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
                $this->replace($profile, $relation, $content[$payloadKey] ?? []);
            }

            return $profile;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replace(PortfolioProfile $profile, string $relation, array $items): void
    {
        $profile->{$relation}()->delete();

        foreach (array_values($items) as $index => $item) {
            // is_visible only where the relation has the column: social links
            // derive theirs from their two placements, and MySQL rejects an
            // INSERT naming a generated column.
            $defaults = ['sort_order' => $index + 1];

            if (in_array('is_visible', PortfolioFields::CHILDREN[$relation], true)) {
                $defaults['is_visible'] = true;
            }

            $profile->{$relation}()->create($item + $defaults);
        }
    }
}
