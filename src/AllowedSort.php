<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Sort;
use RoundlyConsulting\QueryBuilder\Enums\SortDirection;
use RoundlyConsulting\QueryBuilder\Sorts\CustomSort;
use RoundlyConsulting\QueryBuilder\Sorts\FieldSort;

final class AllowedSort
{
    private function __construct(
        public readonly string $name,
        public readonly string $internalName,
        private readonly Sort $sort,
    ) {}

    public static function field(string $name, ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new FieldSort);
    }

    public static function custom(string $name, Sort $sort, ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new CustomSort($sort));
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, SortDirection $direction): void
    {
        $this->sort->apply($query, $direction, $this->internalName);
    }
}
