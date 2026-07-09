<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Support\LikeEscaper;

final readonly class PartialFilter implements Filter
{
    /**
     * @param  bool  $leadingWildcard  prepend `%` (match any prefix)
     * @param  bool  $trailingWildcard  append `%` (match any suffix)
     */
    public function __construct(
        private bool $leadingWildcard = true,
        private bool $trailingWildcard = true,
    ) {}

    /**
     * Case-insensitive partial match, portable across sqlite/mysql/pgsql via the
     * query builder's `whereLike(..., caseSensitive: false)` (pgsql uses ILIKE,
     * others a collation-insensitive LIKE). User wildcards are escaped so `%`/`_`
     * can't widen the match. Both wildcard flags default true (a `%value%`
     * "contains"); anchoring one side yields begins-/ends-with. An array value
     * produces a grouped OR of matches.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $values = is_array($value) ? $value : [$value];

        $prefix = $this->leadingWildcard ? '%' : '';
        $suffix = $this->trailingWildcard ? '%' : '';

        $query->where(function (Builder $query) use ($values, $property, $prefix, $suffix): void {
            foreach ($values as $item) {
                $needle = $prefix.LikeEscaper::escape((string) $item).$suffix;

                $query->orWhereLike($property, $needle);
            }
        });
    }
}
