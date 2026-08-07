<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * The SQL comparison operator an OperatorFilter applies server-side.
 *
 * Chosen in allow-list config, never read from the request — the wire stays
 * `filter[<name>]=<value>`; the operator is not part of the query grammar.
 *
 * {@see RequestedOperator} is the deliberate counterpart, for the filters that let a client
 * PICK a comparison from a declared set. Its values are URL tokens rather than SQL, and the
 * allow-list is what turns a token into one of these.
 */
enum FilterOperator: string
{
    use Helpers;

    case Equal = '=';
    case NotEqual = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
}
