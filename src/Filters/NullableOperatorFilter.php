<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Concerns\DeclaresEqualityOperators;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\DataTransferObjects\RequestedFilterValues;
use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\Support\FilterSentinel;

/**
 * Exact match on a NULLABLE column, with a sentinel for "unset" and a client-choosable
 * negation.
 *
 * {@see ExactFilter} cannot serve such a column, because the two things a person most
 * wants from it are unsayable in a bare value:
 *
 * - **"has none of these"** — `filter[project]=none` matches the rows with no project at
 *   all. The sentinel is a literal value on the wire (`none` by default), because a
 *   nullable column has no other way to spell "unset" in a URL: an empty
 *   `filter[project]=` is indistinguishable from the filter being absent.
 * - **"has any of these"** — `filter[project]=not:none`, the negation of the sentinel,
 *   which is why `not:` cannot be delegated to {@see NotEqualFilter}: there `not:none`
 *   would mean `!= 'none'` and match every row.
 *
 * **A negation includes rows with no value.** "Not this project" plainly covers a row with
 * no project — the reading SQL's own `!= ` gets wrong (see {@see NotEqualFilter}), and the
 * only case where it does not apply is the sentinel itself, which the client negates
 * explicitly.
 *
 * **Values are shape-checked before they reach the driver** ({@see FilterValueShape}), so
 * a malformed id answers with an empty result instead of a 500 from a typed column. When
 * every value was the wrong shape the filter matches NOTHING rather than dropping itself:
 * dropping it would show the unfiltered list, which reads as "the filter worked and
 * everything matched".
 */
final readonly class NullableOperatorFilter implements Filter
{
    use DeclaresEqualityOperators;

    /** @var list<RequestedOperator> */
    private array $allowed;

    /**
     * @param  list<RequestedOperator>  $operators  what the client may ask for, beyond the
     *                                              implicit `is`
     */
    public function __construct(
        private FilterValueShape $shape = FilterValueShape::Text,
        array $operators = [RequestedOperator::Not],
        private string $sentinel = FilterSentinel::NONE,
    ) {
        $this->allowed = $this->equalityOperators($operators, 'NullableOperatorFilter');
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $parsed = RequestedFilterValues::parse($value, $this->allowed);

        $unset = $parsed->contains($this->sentinel);
        $named = $parsed->without($this->sentinel);
        $usable = RequestedFilterValues::matching($named, $this->shape);

        if ($named === [] && ! $unset) {
            return;
        }

        if ($parsed->negated()) {
            $unset
                ? $query->whereNotNull($property)->whereNotIn($property, $usable)
                : $query->where(
                    fn (Builder $inner) => $inner->whereNotIn($property, $usable)->orWhereNull($property),
                );

            return;
        }

        if ($named !== [] && $usable === []) {
            // Every named value was the wrong shape for the column, so nothing can match.
            $unset ? $query->whereNull($property) : $query->whereIn($property, []);

            return;
        }

        match (true) {
            $usable === [] => $query->whereNull($property),
            $unset => $query->where(
                fn (Builder $inner) => $inner->whereIn($property, $usable)->orWhereNull($property),
            ),
            default => $query->whereIn($property, $usable),
        };
    }
}
