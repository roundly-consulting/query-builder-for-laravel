<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\PackageToolkit\Support\LikeEscaper;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Exceptions\InvalidFilterDeclaration;
use RoundlyConsulting\QueryBuilder\Support\LikeClause;
use RoundlyConsulting\QueryBuilder\Support\QueryBuilderConfig;

/**
 * One search box across several columns: rows where ANY of the columns contains the phrase.
 *
 * - **One phrase.** A search box holds one piece of text, so a comma is part of it: the
 *   request's comma split is rejoined (`Smith, John` searches for `Smith, John`, never for
 *   `Smith` or `John`). That differs on purpose from `partial()`, where a list is an OR of
 *   values. The phrase is capped at `limits.max_value_length` after the join and trimmed
 *   (`mb_trim`, so a non-breaking space goes too); an empty phrase — or one of nothing but
 *   commas, which is how a lone comma arrives — adds no constraint. No operator prefix is
 *   read: `not:x` is searched as text.
 * - **Literal text.** `%`, `_` and `\` are escaped and matched with an explicit `escape ?`, so
 *   they are literal on every engine. A NULL column simply does not match; the row is still
 *   found through another column.
 * - **Grouped.** `(a ilike ? escape ? or b ilike ? escape ?)` — one group, ANDed with the
 *   other filters. `ilike` on Postgres, `like` everywhere else (case folding as
 *   {@see PartialFilter}'s); an unmodelled driver degrades to `like` and never throws.
 * - **Index-friendly.** A column is never lowercased and never cast unless it is listed in
 *   `$asText`, so a `gin_trgm_ops` index on it stays usable. Postgres has no `ILIKE` for
 *   json/jsonb, uuid, integer, inet or native enum columns (SQLSTATE 42883): list those in
 *   `$asText` — `::text` on Postgres, `cast(… as char)` on MySQL/MariaDB (which also makes a
 *   JSON value follow the connection collation instead of comparing as binary), unchanged on
 *   SQLite.
 *
 * Columns are bare (`name`) or table-qualified (`users.name`, after a join) and wrapped through
 * the grammar; they come from the declaration, so `apply()` ignores its `$property`. There is
 * no relation search: join and qualify the columns, or use a `callback` with `whereHas`.
 */
final readonly class SearchFilter implements Filter
{
    /** A bare or table-qualified column name — nothing an expression could hide in. */
    private const string COLUMN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    /** @var list<string> */
    private array $columns;

    /** @var list<string> */
    private array $asText;

    /**
     * @param  list<string>  $columns  the columns to search, bare or table-qualified
     * @param  list<string>  $asText  those of `$columns` to compare as text: every one that is
     *                                not a text column (json/jsonb, uuid, integer, inet, enum)
     *
     * @throws InvalidFilterDeclaration for no columns, a column that is not a plain name,
     *                                  or an `$asText` column that is not searched
     */
    public function __construct(array $columns, array $asText = [])
    {
        if ($columns === []) {
            throw InvalidFilterDeclaration::noColumns();
        }

        foreach ($columns as $column) {
            if (preg_match(self::COLUMN, $column) !== 1) {
                throw InvalidFilterDeclaration::notAColumn($column);
            }
        }

        $this->columns = array_values(array_unique($columns));

        foreach ($asText as $column) {
            if (! in_array($column, $this->columns, true)) {
                throw InvalidFilterDeclaration::asTextNotSearched($column, $this->columns);
            }
        }

        $this->asText = array_values(array_unique($asText));
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $phrase = self::phrase($value);

        if ($phrase === null) {
            return;
        }

        $needle = '%'.LikeEscaper::escape($phrase).'%';

        $conditions = array_map(
            fn (string $column) => LikeClause::condition($query, $this->columnSql($query, $column)),
            $this->columns,
        );

        $query->where(function (Builder $query) use ($conditions, $needle): void {
            foreach ($conditions as $condition) {
                $query->whereRaw($condition, [$needle, LikeClause::ESCAPE], 'or');
            }
        });
    }

    /**
     * The column as the grammar wraps it, cast to text when it is declared in `$asText`.
     *
     * @param  Builder<Model>  $query
     */
    private function columnSql(Builder $query, string $column): string
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);

        if (! in_array($column, $this->asText, true)) {
            return $wrapped;
        }

        return match (LikeClause::driver($query)) {
            DatabaseDriver::Pgsql => "{$wrapped}::text",
            DatabaseDriver::Mysql, DatabaseDriver::Mariadb => "cast({$wrapped} as char)",
            default => $wrapped,
        };
    }

    /**
     * The text to search for, or null when there is none.
     */
    private static function phrase(mixed $value): ?string
    {
        $text = is_array($value)
            ? implode(',', array_filter(array_map(self::text(...), $value), static fn (?string $piece): bool => $piece !== null))
            : self::text($value) ?? '';

        $phrase = mb_trim(mb_substr($text, 0, QueryBuilderConfig::maxValueLength()));

        // A lone comma reaches a filter as two empty elements, and an empty element is no
        // value: a phrase of nothing but commas and whitespace carries no text to search.
        return mb_trim(str_replace(',', '', $phrase)) === '' ? null : $phrase;
    }

    /**
     * A scalar spelled as text (`true` → `'true'`); null for anything else.
     */
    private static function text(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            default => null,
        };
    }
}
