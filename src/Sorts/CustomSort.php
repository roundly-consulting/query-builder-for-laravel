<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Sort;
use RoundlyConsulting\QueryBuilder\Enums\SortDirection;

final class CustomSort implements Sort
{
    public function __construct(
        private readonly Sort $sort,
    ) {}

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, SortDirection $direction, string $property): void
    {
        $this->sort->apply($query, $direction, $property);
    }
}
