<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\DataTransferObjects;

use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;

/**
 * One `filter[<name>]` value, split into the operator the request named and the value
 * that remains.
 *
 * `$operator` is **nullable on purpose**: `null` means the request named no operator at
 * all, which is a different fact from "the request named `is`". Only the first takes the
 * filter's configured default, and a search field whose default is `contains` has to tell
 * the two apart — collapsing them is how `is:` silently became an always-available token.
 */
final readonly class RequestedFilterValue
{
    public function __construct(
        public ?RequestedOperator $operator,
        public string $value,
    ) {}

    /** The operator to apply: the one the request named, else the filter's default. */
    public function operatorOr(RequestedOperator $default): RequestedOperator
    {
        return $this->operator ?? $default;
    }
}
