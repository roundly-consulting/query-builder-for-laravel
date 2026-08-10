<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\DataTransferObjects\RequestedFilterValues;
use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\Exceptions\UnsupportedOperator;

/**
 * Match on a RELATION rather than a column, with a client-choosable negation.
 *
 * The filter's internal name is the relation to constrain; the column being matched inside
 * it is given here, qualified (`labels.id`), because the subquery joins another table and
 * a bare `id` there is ambiguous.
 *
 * **A negation is `whereDoesntHave`, never a negated `whereHas`.** For a to-many relation
 * the two are different questions: a row related to several labels must be EXCLUDED when
 * any one of them is negated, but `whereHas('labels', fn ($q) => $q->where('id', '!=', $x))`
 * keeps it — the row still has some other label, and that other label satisfies the
 * subquery. It is the commonest way a "not" filter silently returns almost everything.
 *
 * Values are shape-checked ({@see FilterValueShape}) before they reach the driver, and a
 * value of the wrong shape matches no row — so a positive match answers empty, while a
 * negation excludes nothing (a row cannot be related to an id that cannot exist).
 */
final readonly class RelationOperatorFilter implements Filter
{
    /** @var list<RequestedOperator> */
    private array $allowed;

    /**
     * @param  string  $column  the qualified column inside the relation (`labels.id`)
     * @param  list<RequestedOperator>  $operators  what the client may ask for, beyond the
     *                                              implicit `is`
     * @param  ?string  $sentinel  the value meaning "related to nothing at all"
     *                             ({@see FilterSentinel::NONE}); `null` offers no sentinel,
     *                             for a relation where "has none" is not a question anybody
     *                             asks
     */
    public function __construct(
        private string $column,
        private FilterValueShape $shape = FilterValueShape::Text,
        array $operators = [RequestedOperator::Not],
        private ?string $sentinel = null,
    ) {
        $this->allowed = $this->equalityOperators($operators, 'RelationOperatorFilter');
    }

    /**
     * @param  Builder<Model>  $query
     * @param  string  $property  the relation name
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $parsed = RequestedFilterValues::parse($value, $this->allowed);

        if ($parsed->values === []) {
            return;
        }

        $empty = $this->sentinel !== null && $parsed->contains($this->sentinel);
        $named = $this->sentinel === null ? $parsed->values : $parsed->without($this->sentinel);
        $usable = RequestedFilterValues::matching($named, $this->shape);

        if ($empty) {
            $this->applyWithSentinel($query, $property, $usable, $parsed->negated());

            return;
        }

        if ($usable === []) {
            // No row can be related to a value the key column cannot hold. Matching that is
            // an empty result; excluding it excludes nothing.
            if (! $parsed->negated()) {
                $query->whereIn($query->getModel()->getQualifiedKeyName(), []);
            }

            return;
        }

        $matches = fn (Builder $related) => $related->whereIn($this->column, $usable);

        $parsed->negated()
            ? $query->whereDoesntHave($property, $matches)
            : $query->whereHas($property, $matches);
    }

    /**
     * The sentinel was asked for: "related to nothing", or its negation "related to
     * something".
     *
     * Mixed with named values it is an OR on the positive side — `none,<id>` is "no
     * department, or this one" — and an AND on the negative side, because `not:none,<id>`
     * negates the whole filter: "has a department, and not that one".
     *
     * @param  Builder<Model>  $query
     * @param  list<string>  $usable
     */
    private function applyWithSentinel(Builder $query, string $property, array $usable, bool $negated): void
    {
        $matches = fn (Builder $related) => $related->whereIn($this->column, $usable);

        if ($negated) {
            $query->whereHas($property);

            if ($usable !== []) {
                $query->whereDoesntHave($property, $matches);
            }

            return;
        }

        $usable === []
            ? $query->whereDoesntHave($property)
            : $query->where(function (Builder $inner) use ($property, $matches): void {
                $inner->whereDoesntHave($property)->orWhereHas($property, $matches);
            });
    }

    /**
     * The operators an equality filter can answer, and the guard that says so.
     *
     * @param  list<RequestedOperator>  $operators
     * @return list<RequestedOperator>
     */
    private function equalityOperators(array $operators, string $filter): array
    {
        foreach ($operators as $operator) {
            if ($operator !== RequestedOperator::Is && $operator !== RequestedOperator::Not) {
                throw UnsupportedOperator::make(
                    $operator,
                    [RequestedOperator::Is, RequestedOperator::Not],
                    $filter,
                );
            }
        }

        // `is` is always nameable: a client that switched a chip has to be able to switch
        // back. `array_unique` may reorder; only membership is ever read.
        return array_values(array_unique([RequestedOperator::Is, ...$operators], SORT_REGULAR));
    }
}
