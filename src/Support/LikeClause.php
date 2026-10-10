<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;

/**
 * The one `LIKE` clause every text filter in this package compiles: `{column} like ? escape ?`,
 * `ilike` on Postgres, with the needle and the escape character both bound.
 *
 * The explicit `ESCAPE` is what keeps an escaped `%` / `_` literal on every engine — SQLite has
 * no default escape character, so a plain `LIKE` would leave the escaped wildcards live (the
 * needle is escaped with the toolkit's `LikeEscaper`, whose escape character is
 * {@see self::ESCAPE}). The column is SQL the caller compiled from a developer-supplied
 * identifier through the grammar; no request input ever reaches an identifier position.
 *
 * @internal
 */
final class LikeClause
{
    /** The escape character `LikeEscaper` escapes with, bound beside every needle. */
    public const string ESCAPE = '\\';

    /**
     * `{column} like ? escape ?` — `ilike` on Postgres, `not like` / `not ilike` when negated.
     * Bind `[$needle, self::ESCAPE]` with it.
     *
     * @param  Builder<Model>  $query
     * @param  string  $column  grammar-compiled SQL for a developer-supplied column, never input
     */
    public static function condition(Builder $query, string $column, bool $negated = false): RawExpression
    {
        $operator = self::isPgsql($query) ? 'ilike' : 'like';

        if ($negated) {
            $operator = "not {$operator}";
        }

        return new RawExpression("{$column} {$operator} ? escape ?");
    }

    /**
     * Whether the query's connection speaks Postgres (the only driver with a native
     * case-insensitive `ILIKE`).
     *
     * `DatabaseDriver::tryFrom()`, not `::current()`: the toolkit's enum models the four
     * drivers a package may special-case and `current()` *throws* for anything else. A filter
     * runs inside a request, so an unmodelled driver (`sqlsrv`, a host's custom connection)
     * must degrade to the portable `like` — as it always has — never turn a working list
     * endpoint into a 500.
     *
     * @param  Builder<Model>  $query
     */
    private static function isPgsql(Builder $query): bool
    {
        return self::driver($query)?->isPgsql() ?? false;
    }

    /**
     * The query's driver, or null for one the toolkit does not model.
     *
     * @param  Builder<Model>  $query
     */
    public static function driver(Builder $query): ?DatabaseDriver
    {
        return DatabaseDriver::tryFrom($query->getModel()->getConnection()->getDriverName());
    }
}
