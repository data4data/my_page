<?php

namespace App\Http\Resources;

use App\Support\PortfolioFields;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use LogicException;

/**
 * One row of an ordered child collection, as exactly the fields it is made of.
 * Drops `id`, the timestamps and `sort_order` — position is the order of the
 * array, which is the only thing the page reads.
 */
class PortfolioItemResource extends JsonResource
{
    /**
     * @param  list<string>  $keys
     */
    public function __construct(Model $resource, private array $keys)
    {
        parent::__construct($resource);
    }

    /**
     * Every row of one relation, resolved to plain arrays — the JSON the
     * endpoints return and the snapshot PortfolioRevision stores.
     *
     * @param  Collection<int, Model>|EloquentCollection<int, Model>  $items
     * @return list<array<string, mixed>>
     */
    public static function forRelation(Collection $items, string $relation): array
    {
        $keys = PortfolioFields::CHILDREN[$relation]
            ?? throw new LogicException("No key list for the [{$relation}] relation.");

        return $items
            ->map(fn (Model $item): array => (new self($item, $keys))->resolve())
            ->values()
            ->all();
    }

    /**
     * Unusable: the parent builds members with `new static($item)`, which
     * cannot say which relation's keys to use.
     *
     * @param  mixed  $resource
     */
    public static function collection($resource): never
    {
        throw new LogicException(self::class.'::collection() cannot pick a key list — use forRelation().');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->only($this->keys);
    }
}
