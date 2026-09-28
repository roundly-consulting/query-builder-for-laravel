<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Exceptions;

use LogicException;

/**
 * An allow-list or default sort was DECLARED after the request had already been applied.
 *
 * `QueryBuilder` applies the request the first time a call is forwarded to the underlying
 * builder — `where()`, `with()`, `get()`, anything — because it cannot tell a call that only
 * adds a constraint from one that runs the query. A declaration arriving after that point
 * cannot take effect: the requested keys were already checked against the allow-list as it
 * stood, so a filter the controller meant to allow has answered 400, and a default sort has
 * silently gone missing.
 *
 * Refusing it is the only honest answer. It is a developer mistake, never a request one —
 * hence a `LogicException` rather than an HTTP status — and it is thrown whatever the
 * request contains, so a misordered chain fails on its first request in development instead
 * of on the first client that happens to filter.
 */
final class AllowListAlreadyApplied extends LogicException implements QueryBuilderException
{
    public static function make(string $declaration, string $appliedBy): self
    {
        return new self(
            "QueryBuilder::{$declaration}() was called after the request had already been applied to the query by {$appliedBy}(). "
            .'Declare allowedFilters(), allowedSorts() and defaultSort() before any builder call.',
        );
    }
}
