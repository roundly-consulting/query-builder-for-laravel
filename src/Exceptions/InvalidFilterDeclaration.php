<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Exceptions;

use LogicException;

/**
 * A filter was DECLARED with arguments it cannot work with — a search with no columns, a
 * column that is really an SQL expression, an `asText` column it does not search.
 *
 * Thrown at construction: a developer mistake, never a request one, so a `LogicException`
 * rather than an HTTP status, and it fails on the first request in development instead of
 * compiling SQL nobody meant. Like {@see UnsupportedOperator}, the message is developer-facing
 * English and not translated.
 */
final class InvalidFilterDeclaration extends LogicException implements QueryBuilderException
{
    public static function noColumns(): self
    {
        return new self('A search filter needs at least one column to search.');
    }

    public static function notAColumn(string $column): self
    {
        return new self(
            "A search filter cannot search `{$column}`: a column is a bare or table-qualified name (`name`, `users.name`). "
            .'Declare a column that has to be compared as text in `asText` rather than casting it here.',
        );
    }

    /**
     * @param  list<string>  $columns
     */
    public static function asTextNotSearched(string $column, array $columns): self
    {
        return new self(
            "A search filter lists `{$column}` in `asText`, but does not search it; its columns are ".implode(', ', $columns).'.',
        );
    }
}
