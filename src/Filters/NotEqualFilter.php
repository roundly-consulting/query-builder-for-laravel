<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;

/**
 * `!=`, with the multi-value semantics a NEGATION needs.
 *
 * Not `OperatorFilter(NotEqual)`: that ORs an array of values, and a grouped OR of
 * negations matches nearly every row (a value is always "not" one of two things). A
 * client asking `not:draft,archived` means "neither", so the values are ANDed —
 * `whereNotIn` says exactly that.
 *
 * `OperatorFilter`'s OR is correct for the operators it is used with (`=`, `>`, `<`),
 * so it is left alone rather than made conditional.
 *
 * **A NULL column is included.** SQL's `status != 'draft'` is UNKNOWN — and therefore
 * false — when `status` is NULL, but a person filtering a list for "not draft" plainly
 * expects rows with no status at all.
 */
final readonly class NotEqualFilter implements Filter
{
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

        $query->where(function (Builder $query) use ($values, $property): void {
            $query->whereNotIn($property, $values)->orWhereNull($property);
        });
    }
}
