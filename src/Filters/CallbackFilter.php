<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;

final class CallbackFilter implements Filter
{
    /**
     * @param  Closure(Builder<Model>, mixed, string): void  $callback
     */
    public function __construct(
        private readonly Closure $callback,
    ) {}

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        ($this->callback)($query, $value, $property);
    }
}
