<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Support\LikeEscaper;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Support\LikeClause;

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
     * Partial match, escaped the same way on sqlite/mysql/pgsql. User
     * wildcards are escaped and matched with an explicit `ESCAPE '\'` clause so
     * `%`/`_` stay literal on every driver — SQLite has no default escape
     * character, so a plain `LIKE` would leave the escaped wildcards live.
     *
     * Case folding is the engine's and differs beyond ASCII: Postgres' `ILIKE`
     * follows the database's ctype locale (Unicode under a UTF-8 locale), MySQL's
     * `LIKE` follows the column collation (the default `_ci` ones fold case and
     * accents, `_bin`/`_cs` do not), and SQLite's `LIKE` folds ASCII letters only
     * — `ärger` does not find `Ärger` there. The column identifier is developer-supplied
     * (grammar-wrapped `internalName`), the needle and escape char are bound —
     * no request input ever reaches an identifier position. Both wildcard flags
     * default true (a `%value%` "contains"); anchoring one side yields
     * begins-/ends-with. An array value produces a grouped OR of matches.
     *
     * An empty element is no value and is dropped — a `like '%%'` would match every
     * non-null row, swallowing the real needle beside it and hiding the NULL rows — and
     * a filter left with nothing adds no constraint.
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

        $prefix = $this->leadingWildcard ? '%' : '';
        $suffix = $this->trailingWildcard ? '%' : '';

        // Raw so we can attach an explicit `ESCAPE '\'` clause the query builder
        // never emits. The column is a grammar-wrapped, developer-supplied
        // identifier (never request input); the needle and escape char are
        // bound — no user input reaches an identifier slot.
        $condition = LikeClause::condition($query, $query->getQuery()->getGrammar()->wrap($property));

        $query->where(function (Builder $query) use ($values, $prefix, $suffix, $condition): void {
            foreach ($values as $item) {
                $needle = $prefix.LikeEscaper::escape((string) $item).$suffix;

                $query->whereRaw($condition, [$needle, LikeClause::ESCAPE], 'or');
            }
        });
    }
}
