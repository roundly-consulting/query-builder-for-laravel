<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Tests\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;

final class EvenViewsFilter implements Filter
{
    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $query->whereRaw('views % 2 = 0');
    }
}
