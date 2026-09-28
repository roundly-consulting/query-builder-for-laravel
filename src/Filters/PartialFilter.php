<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\PackageToolkit\Support\LikeEscaper;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
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
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $values = is_array($value) ? $value : [$value];

        $prefix = $this->leadingWildcard ? '%' : '';
        $suffix = $this->trailingWildcard ? '%' : '';

        $operator = $this->isPgsql($query) ? 'ilike' : 'like';
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

    /**
     * Whether the query's connection speaks Postgres (the only driver with a
     * native case-insensitive `ILIKE`).
     *
     * `DatabaseDriver::tryFrom()`, not `::current()`: the toolkit's enum models
     * the four drivers a package may special-case and `current()` *throws* for
     * anything else. A filter runs inside a request, so an unmodelled driver
     * (`sqlsrv`, a host's custom connection) must degrade to the portable
     * `like` — as it always has — never turn a working list endpoint into a 500.
     *
     * @param  Builder<Model>  $query
     */
    private function isPgsql(Builder $query): bool
    {
        $driver = DatabaseDriver::tryFrom($query->getModel()->getConnection()->getDriverName());

        return $driver?->isPgsql() ?? false;
    }
}
