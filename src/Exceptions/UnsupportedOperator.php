<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Exceptions;

use LogicException;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;

/**
 * A filter was DECLARED with an operator it cannot perform.
 *
 * Thrown at construction — a developer mistake, never a request one, which is why it is a
 * `LogicException` and not an HTTP status. {@see NullableOperatorFilter},
 * {@see RelationOperatorFilter} and {@see JsonContainsOperatorFilter} each answer exactly
 * two questions ("is one of these" / "is none of these"), so a `gte` or `contains` in their
 * operator list has no behaviour to map onto.
 *
 * Failing loudly matters because the silent alternative is the worst kind of bug: the token
 * is stripped off the wire, the filter applies plain equality to what remains, and the query
 * means the OPPOSITE of what the client asked for.
 */
final class UnsupportedOperator extends LogicException implements QueryBuilderException
{
    /**
     * @param  list<RequestedOperator>  $supported
     */
    public static function make(RequestedOperator $operator, array $supported, string $filter): self
    {
        $names = implode(', ', array_map(static fn (RequestedOperator $o): string => $o->value, $supported));

        return new self("{$filter} cannot perform the `{$operator->value}` operator; it supports {$names}.");
    }
}
