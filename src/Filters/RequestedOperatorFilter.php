<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;

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
     */
    public function __construct(array $allowed, bool $partialByDefault = false)
    {
        $this->default = $partialByDefault ? RequestedOperator::Contains : RequestedOperator::Is;

        // The filter's OWN default is always nameable — saying it explicitly grants no
        // capability the developer did not already choose, and a client that switched to
        // another operator has to be able to switch back. Nothing else is added: on a
        // `partialByDefault` search field `is:` is NOT an operator, so pasting `is:done`
        // into the box searches for that text instead of silently exact-matching `done`.
        // `array_unique` may reorder; only membership is ever read.
        $this->allowed = array_values(array_unique([$this->default, ...$allowed], SORT_REGULAR));
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

        // Only a STRING can carry an operator prefix. `QueryBuilderRequest` normalizes
        // `true`/`false` to real booleans, and casting one to a string to look for a `:`
        // destroys it — `false` becomes `''`, which Postgres rejects outright on a
        // boolean column (`invalid input syntax for type boolean`) and mysql/sqlite
        // quietly coerce to `0`. So a non-string value is forwarded exactly as received.
        if (! is_string($first)) {
            $this->delegate($this->default)->apply($query, $value, $property);

            return;
        }

        $parsed = RequestedOperator::split($first, $this->allowed);
        $values[0] = $parsed->value;

        $this->delegate($parsed->operatorOr($this->default))
            ->apply($query, is_array($value) ? $values : $parsed->value, $property);
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
