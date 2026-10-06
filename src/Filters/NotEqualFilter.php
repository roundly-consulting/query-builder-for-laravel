<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;

/**
 * `!=`, with the multi-value semantics a NEGATION needs.
 *
 * A client asking `not:draft,archived` means "neither", so the values are ANDed —
 * `whereNotIn` says exactly that, as `OperatorFilter(NotEqual)` does for a list. What
 * this adds over that plain-SQL server-side operator is the NULL reading below.
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
