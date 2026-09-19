<?php

namespace App\Http\Resources;

use App\Models\PortfolioProfile;
use App\Support\PortfolioFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The profile as exactly the fields the page is made of.
 *
 * Built from PortfolioFields::PROFILE — the same list save()
 * writes — so the endpoint can only ever hand back what the editor can put
 * there. `GET /portfolio` needs no login, and returning the model instead
 * published `id`, `slug`, `type`, `is_active` and the timestamps, and would
 * have published every column added after it with no code change at all.
 *
 * Keying it to the write list rather than to a list of its own is the point:
 * a new content field is published because it was made editable, and nothing
 * else ever is.
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
        // only() reads through the casts, so the translated columns arrive as
        // {en, nl} arrays rather than as the raw JSON strings.
        return $this->resource->only(PortfolioFields::PROFILE);
    }
}
