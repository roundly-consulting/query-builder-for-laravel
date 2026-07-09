<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * The SQL comparison operator an OperatorFilter applies server-side.
 *
 * Chosen in allow-list config, never read from the request — the wire stays
 * `filter[<name>]=<value>`; the operator is not part of the query grammar.
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
