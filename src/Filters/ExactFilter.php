<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;

final class ExactFilter implements Filter
{
    /**
     * An empty element is no value (`a,,b` is two values, a lone `,` none at all), and a
     * filter left with nothing adds no constraint — never `= ''`, which matches no row and
     * is a driver error on a typed Postgres column. A real `false` or `0` is a value.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $values = array_values(array_filter(
            is_array($value) ? $value : [$value],
            static fn (mixed $item): bool => $item !== null && $item !== '',
        ));

        if ($values === []) {
            return;
        }

        if (is_array($value)) {
            $query->whereIn($property, $values);

            return;
        }

        $query->where($property, '=', $value);
    }
}
