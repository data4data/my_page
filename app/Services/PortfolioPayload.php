<?php

namespace App\Services;

use App\Http\Resources\PortfolioItemResource;
use App\Http\Resources\PortfolioProfileResource;
use App\Models\PortfolioProfile;
use App\Support\PortfolioFields;

/**
 * The shape every read of the page returns — the public endpoint, the admin
 * endpoint, and the JSON a PortfolioRevision stores.
 */
class PortfolioPayload
{
    /**
     * @param  bool  $publicOnly  Drop the rows the owner has switched off.
     * @return array<string, mixed>
     */
    public function forProfile(PortfolioProfile $profile, bool $publicOnly): array
    {
        $visible = fn ($items) => $publicOnly ? $items->where('is_visible', true)->values() : $items->values();

        $payload = ['profile' => PortfolioProfileResource::make($profile)->resolve()];

        foreach (PortfolioFields::RELATIONS as $payloadKey => $relation) {
            $payload[$payloadKey] = PortfolioItemResource::forRelation($visible($profile->{$relation}), $relation);
        }

        return $payload;
    }
}
