<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\DataTransferObjects;

use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;

/**
 * A whole `filter[<name>]` parameter — the operator the request named, and the list of
 * values that remains — for the filters that take MANY values at once.
 *
 * The sibling of {@see RequestedFilterValue}, which carries the single-value case. The
 * split exists because a multi-value filter has to answer three questions the single-value
 * one never faces, and every filter that answered them for itself answered them
 * differently:
 *
 * - **Which element carries the operator?** Only the first. An operator applies to the
 *   filter, not to a value, so `not:a,b` reads as "neither a nor b" — never as "not a, or
 *   b".
 * - **What happens to a non-string element?** It is dropped, not stringified. A request
 *   can nest an array inside a filter parameter, and casting one to a string is an `Array
 *   to string conversion` warning that Laravel's error handler promotes to an exception —
 *   one malformed query string taking a whole list endpoint down behind a generic "500".
 *   A filter that quietly matches the text `"Array"` would be worse still.
 * - **What is the empty string?** Absent. `a,,b` is two values, and a lone `,` is no
 *   filter at all rather than a filter for nothing.
 */
final readonly class RequestedFilterValues
{
    /**
     * @param  list<string>  $values
     */
    public function __construct(
        public ?RequestedOperator $operator,
        public array $values,
    ) {}

    /**
     * Split a raw filter parameter, honouring only the operators `$allowed` names.
     *
     * A token outside that list is not an operator: the whole string stays the value, so a
     * hostile or simply unknown prefix is inert text rather than an error. That list is the
     * entire security boundary, and nothing is added to it here.
     *
     * @param  list<RequestedOperator>  $allowed
     */
    public static function parse(mixed $raw, array $allowed): self
    {
        $values = is_array($raw) ? array_values($raw) : [$raw];
        $first = $values[0] ?? null;
        $operator = null;

        if (is_string($first)) {
            $parsed = RequestedOperator::split($first, $allowed);
            $operator = $parsed->operator;
            $values[0] = $parsed->value;
        }

        $strings = array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            $values,
        );

        return new self(
            $operator,
            array_values(array_filter($strings, static fn (string $value): bool => $value !== '')),
        );
    }

    /** Did the request ask for a negation? */
    public function negated(): bool
    {
        return $this->operator === RequestedOperator::Not;
    }

    /** Is the sentinel among the values? */
    public function contains(string $sentinel): bool
    {
        return in_array($sentinel, $this->values, true);
    }

    /**
     * The values with the sentinel removed — the ones that name an actual row.
     *
     * @return list<string>
     */
    public function without(string $sentinel): array
    {
        return array_values(array_filter(
            $this->values,
            static fn (string $value): bool => $value !== $sentinel,
        ));
    }

    /**
     * The subset of `$values` the column can actually hold. See {@see FilterValueShape}.
     *
     * @param  list<string>  $values
     * @return list<string>
     */
    public static function matching(array $values, FilterValueShape $shape): array
    {
        return array_values(array_filter($values, $shape->matches(...)));
    }
}
