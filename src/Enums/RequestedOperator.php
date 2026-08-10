<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Enums;

use RoundlyConsulting\Enums\Helpers;
use RoundlyConsulting\QueryBuilder\DataTransferObjects\RequestedFilterValue;
use RoundlyConsulting\QueryBuilder\Filters\RequestedOperatorFilter;

/**
 * An operator the CLIENT may ask for, on a filter that declares it.
 *
 * This is deliberately a different enum from {@see FilterOperator}, and the difference is
 * the whole security story:
 *
 * - {@see FilterOperator} is the SQL operator a developer fixes server-side. Its `value`
 *   IS the SQL fragment, and it is never influenced by a request.
 * - This enum is a **wire vocabulary**. Its `value` is a URL token (`not`, `contains`),
 *   never SQL, and it is only honoured when the filter was declared with it in
 *   `AllowedFilter::operators($name, [...])` (or is that filter's own default, which the
 *   developer chose just as deliberately). Anything else in the request — an unknown
 *   token, an operator the filter did not declare, a raw SQL fragment — is treated as
 *   part of the VALUE, never as an operator. There is no path from request text to a
 *   query operator that a developer did not enumerate first.
 *
 * The wire is `filter[<name>]=<operator>:<value>`, e.g. `filter[status]=not:open`. A bare
 * `filter[<name>]=<value>` keeps meaning exactly what it always did — equality — so every
 * existing URL, bookmark and deep link keeps working untouched.
 *
 * `:` is the separator because it cannot appear in an enum value or a uuid, reads clearly
 * in a URL, and — unlike a `!`/`~` prefix — cannot collide with a value that legitimately
 * begins with punctuation.
 */
enum RequestedOperator: string
{
    use Helpers;

    case Is = 'is';
    case Not = 'not';
    case Contains = 'contains';
    case NotContains = 'ncontains';
    case StartsWith = 'starts';
    case GreaterThan = 'gt';
    case GreaterThanOrEqual = 'gte';
    case LessThan = 'lt';
    case LessThanOrEqual = 'lte';

    /**
     * Split a raw `filter[<name>]` value into the operator it names and the value.
     *
     * The same parse {@see RequestedOperatorFilter}
     * performs, exposed for a `AllowedFilter::callback()` that has to do its own thing
     * with the operator — a column with a `none` sentinel, a relation that needs
     * `whereDoesntHave` rather than a negated `where`. Those cannot go through the
     * generic filter, and hand-rolling the parse per callback is how the wire format
     * drifts.
     *
     * The `$allowed` list is the security boundary, and it is the WHOLE of it: a token
     * that is not in it is not an operator, and the whole string comes back as the value.
     * Nothing is added to it here — a caller that wants `is:` honoured passes `Is`.
     *
     * The returned operator is `null` when the request named none, which is not the same
     * as naming `is`; see {@see RequestedFilterValue}.
     *
     * @param  list<self>  $allowed
     */
    public static function split(string $raw, array $allowed): RequestedFilterValue
    {
        $position = strpos($raw, ':');
        if ($position === false) {
            return new RequestedFilterValue(null, $raw);
        }

        $operator = self::tryFrom(substr($raw, 0, $position));

        if ($operator === null || ! in_array($operator, $allowed, true)) {
            return new RequestedFilterValue(null, $raw);
        }

        return new RequestedFilterValue($operator, substr($raw, $position + 1));
    }

    /**
     * Does this operator EXCLUDE rows rather than select them?
     *
     * The distinction matters wherever a filter has nothing usable left to compare: a
     * negation of nothing excludes nothing (so the filter is a no-op), while a positive
     * match on nothing matches nothing (so the filter must say so).
     */
    public function isNegation(): bool
    {
        return $this === self::Not || $this === self::NotContains;
    }

    /** The fixed SQL operator this maps to, for the comparison family only. */
    public function toFilterOperator(): FilterOperator
    {
        return match ($this) {
            self::Not => FilterOperator::NotEqual,
            self::GreaterThan => FilterOperator::GreaterThan,
            self::GreaterThanOrEqual => FilterOperator::GreaterThanOrEqual,
            self::LessThan => FilterOperator::LessThan,
            self::LessThanOrEqual => FilterOperator::LessThanOrEqual,
            default => FilterOperator::Equal,
        };
    }
}
