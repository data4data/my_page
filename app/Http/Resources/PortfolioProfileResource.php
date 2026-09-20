<?php

namespace App\Http\Resources;

use App\Models\PortfolioProfile;
use App\Support\PortfolioFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The profile as exactly the fields the page is made of. Built from the same
 * list save() writes, so `GET /portfolio` — which needs no login — can only
 * hand back what the editor can put there.
 *
 * @property-read PortfolioProfile $resource
 */
class PortfolioProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // only() reads through the casts, so translated columns arrive as
        // {en, nl} arrays rather than raw JSON strings.
        return $this->resource->only(PortfolioFields::PROFILE);
    }
}
