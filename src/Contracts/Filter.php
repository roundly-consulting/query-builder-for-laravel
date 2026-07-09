<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface Filter
{
    /**
     * Apply the filter to the query for the given (already normalised) value.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void;
}
