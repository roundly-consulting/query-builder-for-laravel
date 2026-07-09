<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Enums\SortDirection;

interface Sort
{
    /**
     * Apply the sort to the query in the given direction.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, SortDirection $direction, string $property): void;
}
