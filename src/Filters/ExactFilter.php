<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;

final class ExactFilter implements Filter
{
    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        if (is_array($value)) {
            $query->whereIn($property, $value);

            return;
        }

        $query->where($property, '=', $value);
    }
}
