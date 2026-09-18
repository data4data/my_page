<?php

namespace App\Http\Resources;

use App\Services\PortfolioContentService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use LogicException;

/**
 * One row of an ordered child collection — a metric, an expertise item, a
 * project, a process step — as exactly the fields that row is made of.
 *
 * The four collections differ only in which keys they carry, and those live
 * in PortfolioContentService::CHILD_KEYS already, so this is one class asked
 * for a relation rather than four classes naming one list each. What it drops
 * is `id`, `portfolio_profile_id`, the timestamps and `sort_order`: position
 * is the order of the array, which is the only thing the page reads.
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
     * Every row of one relation, already resolved to plain arrays.
     *
     * The result is both the JSON the endpoints return and the snapshot
     * PortfolioRevision stores, so it has to be an array either way.
     *
     * @param  Collection<int, Model>|EloquentCollection<int, Model>  $items
     * @return list<array<string, mixed>>
     */
    public static function forRelation(Collection $items, string $relation): array
    {
        $keys = PortfolioContentService::CHILD_KEYS[$relation]
            ?? throw new LogicException("No key list for the [{$relation}] relation.");

        return $items
            ->map(fn (Model $item): array => (new self($item, $keys))->resolve())
            ->values()
            ->all();
    }

    /**
     * Unusable here: the parent builds each member with `new static($item)`,
     * which cannot say which relation's keys to use. Failing loudly beats
     * shipping rows shaped by whatever the default constructor guessed.
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
