<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Enums\FilterOperator;

/**
 * Applies a fixed comparison operator (chosen server-side) against a column.
 * A scalar value → `where($property, $operator->value, $value)`; an array value
 * → a grouped OR of the same comparison (parity with PartialFilter). The value
 * is always bound, never interpolated.
 *
 * The one exception is a LIST under {@see FilterOperator::NotEqual}: an OR of
 * negations matches nearly every row (a value is always "not" one of two things),
 * so the list reads as "none of these" — `whereNotIn`. It stays plain SQL, so a
 * NULL column matches neither form; {@see NotEqualFilter} is the NULL-inclusive
 * negation behind the client-chosen `not:`.
 *
 * An empty element is no value and is dropped; a filter left with nothing adds no
 * constraint, rather than ORing in a comparison against `''`.
 */
final readonly class OperatorFilter implements Filter
{
    public function __construct(
        private FilterOperator $operator,
    ) {}

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $values = array_values(array_filter(
            is_array($value) ? $value : [$value],
            static fn (mixed $item): bool => $item !== null && $item !== '',
        ));

        if ($values === []) {
            return;
        }

        if (is_array($value)) {
            if ($this->operator === FilterOperator::NotEqual) {
                $query->whereNotIn($property, $values);

                return;
            }

            $query->where(function (Builder $query) use ($values, $property): void {
                foreach ($values as $item) {
                    $query->orWhere($property, $this->operator->value, $item);
                }
            });

            return;
        }

        $query->where($property, $this->operator->value, $value);
    }
}
