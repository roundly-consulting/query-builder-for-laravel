<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Tests\Support\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Sort;
use RoundlyConsulting\QueryBuilder\Enums\SortDirection;

final class TitleLengthSort implements Sort
{
    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, SortDirection $direction, string $property): void
    {
        $query->orderByRaw('LENGTH(title) '.$direction->value);
    }
}
