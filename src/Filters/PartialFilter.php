<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Support\LikeEscaper;

final class PartialFilter implements Filter
{
    /**
     * Case-insensitive "contains" match, portable across sqlite/mysql/pgsql via
     * the query builder's `whereLike(..., caseSensitive: false)` (pgsql uses
     * ILIKE, others a collation-insensitive LIKE). User wildcards are escaped
     * so `%`/`_` can't widen the match. An array value produces a grouped OR of
     * partial matches.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $values = is_array($value) ? $value : [$value];

        $query->where(function (Builder $query) use ($values, $property): void {
            foreach ($values as $item) {
                $needle = '%'.LikeEscaper::escape((string) $item).'%';

                $query->orWhereLike($property, $needle);
            }
        });
    }
}
