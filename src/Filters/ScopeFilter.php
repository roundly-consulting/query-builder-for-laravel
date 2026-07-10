<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;

final readonly class ScopeFilter implements Filter
{
    /**
     * @param  bool  $spread  spread an array value across the scope's arguments
     *                        instead of passing it as a single argument. Off by
     *                        default: the request must never control the number
     *                        or position of arguments handed to a host scope
     *                        (it could inject an optional column/operator param).
     *                        Only enable it for a scope you own whose signature
     *                        matches the value shape.
     */
    public function __construct(
        private bool $spread = false,
    ) {}

    /**
     * Call the model's query scope named after the property (camel-cased). By
     * default the normalised value is passed as one argument, so a scope always
     * receives exactly one caller-controlled argument regardless of input.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $scope = Str::camel($property);

        if ($this->spread && is_array($value)) {
            $query->{$scope}(...array_values($value));

            return;
        }

        $query->{$scope}($value);
    }
}
