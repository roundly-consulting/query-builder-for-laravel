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
 * A filter whose operator the client may CHOOSE — from a set the developer declared.
 *
 * The wire is `filter[<name>]=<operator>:<value>`; a bare `filter[<name>]=<value>` still
 * means equality, so every existing URL keeps working.
 *
 * ## Why this is not "reading an operator from the request"
 *
 * The request supplies a **token**, not an operator. The token is looked up in the set
 * this filter was constructed with, and only a hit selects a behaviour:
 *
 * - an unknown token (`drop:`, `1=1:`), or one this filter did not declare, is not an
 *   operator at all — the whole string is treated as the literal VALUE, which is also
 *   the only behaviour that cannot surprise anybody: `filter[name]=http://x` filters for
 *   the text `http://x`;
 * - the selected behaviour is one of a fixed handful of prepared query shapes below. No
 *   request text ever reaches an operator or identifier position.
 *
 * So the blast radius of a hostile prefix is "your filter matched nothing", and a filter
 * that declares no operators behaves exactly like {@see ExactFilter}.
 */
final readonly class RequestedOperatorFilter implements Filter
{
    private RequestedOperator $default;

    /** @var list<RequestedOperator> */
    private array $allowed;

    /**
     * @param  list<RequestedOperator>  $allowed  the operators this filter accepts
     * @param  bool  $partialByDefault  when true a bare value is a `contains` match
     *                                  rather than equality (a text search field)
     * @param  FilterValueShape  $shape  what the column can hold — a value of another
     *                                   shape matches nothing instead of reaching the
     *                                   driver, which is a 500 on a typed column
     */
    public function __construct(
        array $allowed,
        bool $partialByDefault = false,
        private FilterValueShape $shape = FilterValueShape::Text,
    ) {
        $this->default = $partialByDefault ? RequestedOperator::Contains : RequestedOperator::Is;

        // The filter's OWN default is always nameable — saying it explicitly grants no
        // capability the developer did not already choose, and a client that switched to
        // another operator has to be able to switch back. Nothing else is added: on a
        // `partialByDefault` search field `is:` is NOT an operator, so pasting `is:done`
        // into the box searches for that text instead of silently exact-matching `done`.
        // `array_unique` may reorder; only membership is ever read.
        $this->allowed = array_values(array_unique([$this->default, ...$allowed], SORT_REGULAR));

        // A LIKE against a typed column is not a query the driver can run: `smallint ilike ?`
        // and `uuid ilike ?` are both `operator does not exist`. The shape says the column is
        // typed and a partial operator says the opposite, so the declaration is refused here
        // rather than 500ing on the first request that uses it.
        if ($shape === FilterValueShape::Text) {
            return;
        }

        foreach ($this->allowed as $operator) {
            if ($operator->isPartial()) {
                throw UnsupportedOperator::make(
                    $operator,
                    array_values(array_filter($this->allowed, static fn (RequestedOperator $o): bool => ! $o->isPartial())),
                    "A `{$shape->value}`-shaped filter",
                );
            }
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        // An array value is a multi-select (`a,b,c`). Operators are per-filter, not
        // per-value, so the operator is parsed off the FIRST element and applied to all
        // of them — `not:a,b` reads as "neither a nor b", which is the only sensible
        // reading and the one the UI offers.
        $values = is_array($value) ? $value : [$value];
        $first = $values[0] ?? null;
        $operator = $this->default;

        // Only a STRING can carry an operator prefix. The request only ever produces
        // strings, but a filter is also called directly (a custom filter delegating, a
        // test) with a real boolean or number — and casting one to a string to look for a
        // `:` destroys it: `false` becomes `''`, which Postgres rejects outright on a
        // boolean column (`invalid input syntax for type boolean`). So a non-string value
        // carries no operator and is forwarded as received — but it is still
        // SHAPE-CHECKED, because a boolean on a uuid or bigint column is the same 500 the
        // shape exists to prevent, reached through a door the string path does not use.
        if (is_string($first)) {
            $parsed = RequestedOperator::split($first, $this->allowed);
            $values[0] = $parsed->value;
            $operator = $parsed->operatorOr($this->default);
        }

        // An empty element is no value (`a,,b` is two values; `,`, `not:` and an empty
        // parameter are none at all), and a filter with no value adds no constraint —
        // whatever its shape. That is not "nothing survived the shape check" (see
        // compare()): only a value that WAS given can fail to fit the column.
        $present = array_values(array_filter(
            $values,
            static fn (mixed $item): bool => $item !== null && $item !== '',
        ));

        if ($present === []) {
            return;
        }

        $this->compare($query, $operator, $this->usable(is_array($value) ? $present : $present[0]), $property);
    }

    /**
     * Apply the operator to what survived the shape check.
     *
     * `null` means nothing the column could hold survived, so no row can match. A negation
     * therefore excludes nothing, while every other operator matches nothing — and matching
     * nothing has to be SAID (`whereIn(…, [])`), because dropping the filter would show the
     * unfiltered list, which reads as "the filter worked and everything matched".
     *
     * @param  Builder<Model>  $query
     */
    private function compare(Builder $query, RequestedOperator $operator, mixed $usable, string $property): void
    {
        if ($usable === null) {
            if (! $operator->isNegation()) {
                $query->whereIn($property, []);
            }

            return;
        }

        $this->delegate($operator)->apply($query, $usable, $property);
    }

    /**
     * The (non-empty) value narrowed to what the column can hold, or `null` when nothing
     * survives.
     *
     * A no-op for the default {@see FilterValueShape::Text}, which is every filter that
     * did not ask for a shape — so this cannot change how an existing filter behaves.
     */
    private function usable(mixed $value): mixed
    {
        if ($this->shape === FilterValueShape::Text) {
            return $value;
        }

        $strings = array_values(array_filter(
            array_map(
                // A real boolean is spelled out, never cast: `(string) false` is `''`,
                // which would drop it, and `(string) true` is `'1'`, which is an id.
                static fn (mixed $item): string => match (true) {
                    is_bool($item) => $item ? 'true' : 'false',
                    is_scalar($item) => (string) $item,
                    default => '',
                },
                is_array($value) ? $value : [$value],
            ),
            static fn (string $item): bool => $item !== '',
        ));

        $usable = RequestedFilterValues::matching($strings, $this->shape);

        if ($usable === []) {
            return null;
        }

        return is_array($value) ? $usable : $usable[0];
    }

    /** The prepared query shape for an operator. Never assembled from request text. */
    private function delegate(RequestedOperator $operator): Filter
    {
        return match (true) {
            $operator === RequestedOperator::Contains => new PartialFilter,
            $operator === RequestedOperator::StartsWith => new PartialFilter(leadingWildcard: false),
            $operator === RequestedOperator::NotContains => new NotPartialFilter,
            $operator === RequestedOperator::Not => new NotEqualFilter,
            $operator === RequestedOperator::Is => new ExactFilter,
            default => new OperatorFilter($operator->toFilterOperator()),
        };
    }
}
