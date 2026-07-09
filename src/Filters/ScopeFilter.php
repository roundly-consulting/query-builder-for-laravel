<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;

final class ScopeFilter implements Filter
{
    /**
     * Call the model's query scope named after the property (camel-cased). An
     * array value is spread as the scope arguments; a scalar is passed as one.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $scope = Str::camel($property);

        $arguments = is_array($value) ? array_values($value) : [$value];

        $query->{$scope}(...$arguments);
    }
}
