<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Support\LikeEscaper;
use RoundlyConsulting\QueryBuilder\Support\RawExpression;

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
     * Case-insensitive partial match, portable across sqlite/mysql/pgsql. User
     * wildcards are escaped and matched with an explicit `ESCAPE '\'` clause so
     * `%`/`_` stay literal on every driver — SQLite has no default escape
     * character, so a plain `LIKE` would leave the escaped wildcards live.
     * Postgres uses `ILIKE` for case-insensitivity; the others a
     * collation-insensitive `LIKE`. The column identifier is developer-supplied
     * (grammar-wrapped `internalName`), the needle and escape char are bound —
     * no request input ever reaches an identifier position. Both wildcard flags
     * default true (a `%value%` "contains"); anchoring one side yields
     * begins-/ends-with. An array value produces a grouped OR of matches.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $values = is_array($value) ? $value : [$value];

        $prefix = $this->leadingWildcard ? '%' : '';
        $suffix = $this->trailingWildcard ? '%' : '';

        $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $column = $query->getQuery()->getGrammar()->wrap($property);

        // Raw so we can attach an explicit `ESCAPE '\'` clause the query builder
        // never emits. The column is a grammar-wrapped, developer-supplied
        // identifier (never request input) wrapped in an Expression; the needle
        // and escape char are bound — no user input reaches an identifier slot.
        $condition = new RawExpression("{$column} {$operator} ? escape ?");

        $query->where(function (Builder $query) use ($values, $prefix, $suffix, $condition): void {
            foreach ($values as $item) {
                $needle = $prefix.LikeEscaper::escape((string) $item).$suffix;

                $query->whereRaw($condition, [$needle, '\\'], 'or');
            }
        });
    }
}
