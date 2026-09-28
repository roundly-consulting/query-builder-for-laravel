<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Enums\FilterOperator;
use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\Filters\CallbackFilter;
use RoundlyConsulting\QueryBuilder\Filters\ExactFilter;
use RoundlyConsulting\QueryBuilder\Filters\JsonContainsOperatorFilter;
use RoundlyConsulting\QueryBuilder\Filters\NullableOperatorFilter;
use RoundlyConsulting\QueryBuilder\Filters\OperatorFilter;
use RoundlyConsulting\QueryBuilder\Filters\PartialFilter;
use RoundlyConsulting\QueryBuilder\Filters\RelationOperatorFilter;
use RoundlyConsulting\QueryBuilder\Filters\RequestedOperatorFilter;
use RoundlyConsulting\QueryBuilder\Filters\ScopeFilter;
use RoundlyConsulting\QueryBuilder\Filters\TrashedFilter;
use RoundlyConsulting\QueryBuilder\Support\FilterSentinel;

final class AllowedFilter
{
    /**
     * @param  bool  $booleans  hand the filter `true`/`false`/`1`/`0` as real booleans —
     *                          only for the host-code filters that opted in
     */
    private function __construct(
        public readonly string $name,
        public readonly string $internalName,
        private readonly Filter $filter,
        private readonly bool $booleans = false,
    ) {}

    public static function exact(string $name, ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new ExactFilter);
    }

    /**
     * Exact match on a BOOLEAN column: `true` / `false` / `1` / `0` in any letter case,
     * compared as a real boolean; a comma list matches either. Any other value matches no
     * row rather than reaching the driver — Postgres answers `boolean = 'maybe'` with an
     * error, not an empty result.
     *
     * Every other filter receives `true` / `false` as the TEXT that was sent (a title
     * search for "false" is a search), so a flag column says so here. Shorthand for
     * {@see self::operators()} with no operators and {@see FilterValueShape::Boolean} —
     * use that directly to also offer `not:`.
     */
    public static function boolean(string $name, ?string $internalName = null): self
    {
        return new self(
            $name,
            $internalName ?? $name,
            new RequestedOperatorFilter([], shape: FilterValueShape::Boolean),
        );
    }

    public static function partial(string $name, ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new PartialFilter);
    }

    /**
     * Anchored prefix match: `value%` (escaped, case-insensitive).
     */
    public static function beginsWith(string $name, ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new PartialFilter(leadingWildcard: false));
    }

    /**
     * Anchored suffix match: `%value` (escaped, case-insensitive).
     */
    public static function endsWith(string $name, ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new PartialFilter(trailingWildcard: false));
    }

    /**
     * Apply a fixed comparison operator server-side (e.g. `>=`, `<`, `!=`).
     * The operator is chosen here, never read from the request.
     */
    public static function operator(string $name, FilterOperator $operator, ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new OperatorFilter($operator));
    }

    /**
     * A filter whose operator the CLIENT may choose, from the set declared here.
     *
     * The wire is `filter[<name>]=<operator>:<value>` (`not:open`, `contains:acme`); a
     * bare `filter[<name>]=<value>` keeps meaning equality, so no existing URL changes.
     *
     * The operator is still an ALLOW-LIST decision made here, in code — the request only
     * supplies a token, and a token this filter did not declare is treated as part of
     * the value rather than as an operator. Declare only what the column can meaningfully
     * answer: `contains` on a boolean is noise, `gt` on a uuid is nonsense.
     *
     * The filter's own DEFAULT is always nameable on top of `$operators` (`is:` normally,
     * `contains:` when `$partialByDefault`) — saying explicitly what a bare value already
     * means grants no capability, and a client that switched operators has to be able to
     * switch back. Nothing else is implicit: on a search field `is:` is not an operator,
     * so pasting `is:done` into the box searches for that text.
     *
     * @param  list<RequestedOperator>  $operators
     * @param  bool  $partialByDefault  a bare value is a `contains` match rather than
     *                                  equality — for a free-text search field
     * @param  FilterValueShape  $shape  what the column can hold; a value of another shape
     *                                   matches nothing instead of reaching the driver
     */
    public static function operators(
        string $name,
        array $operators,
        ?string $internalName = null,
        bool $partialByDefault = false,
        FilterValueShape $shape = FilterValueShape::Text,
    ): self {
        return new self(
            $name,
            $internalName ?? $name,
            new RequestedOperatorFilter($operators, $partialByDefault, $shape),
        );
    }

    /**
     * Exact match on a NULLABLE column, with a sentinel for "unset" and an optional
     * negation the client may ask for.
     *
     * `filter[project]=none` matches the rows with no project, `filter[project]=not:none`
     * the rows that have one, and a negation of a real value also matches the unset rows —
     * see {@see NullableOperatorFilter} for why none of that can be expressed by
     * {@see self::exact()} or {@see self::operators()}.
     *
     * Declare the column's `$shape` whenever it is typed (a uuid or bigint key): a
     * malformed id then answers with an empty result rather than a 500 from the driver.
     *
     * @param  list<RequestedOperator>  $operators
     */
    public static function nullable(
        string $name,
        ?string $internalName = null,
        FilterValueShape $shape = FilterValueShape::Text,
        array $operators = [RequestedOperator::Not],
        string $sentinel = FilterSentinel::NONE,
    ): self {
        return new self(
            $name,
            $internalName ?? $name,
            new NullableOperatorFilter($shape, $operators, $sentinel),
        );
    }

    /**
     * Match on a RELATION — `whereHas`, and `whereDoesntHave` for a negation.
     *
     * `$column` is qualified (`labels.id`) because the subquery joins another table. Use
     * this rather than a `callback` for any to-many relation: a hand-rolled negation is
     * almost always a negated `whereHas`, which keeps exactly the rows it should exclude
     * ({@see RelationOperatorFilter}).
     *
     * Pass a `$sentinel` where "related to nothing" is a question the list asks — then
     * `filter[department]=none` are the rows spanning no department and `not:none` the ones
     * spanning at least one, matching what {@see self::nullable()} offers for a column.
     *
     * @param  list<RequestedOperator>  $operators
     */
    public static function relation(
        string $name,
        string $relation,
        string $column,
        FilterValueShape $shape = FilterValueShape::Text,
        array $operators = [RequestedOperator::Not],
        ?string $sentinel = null,
    ): self {
        return new self($name, $relation, new RelationOperatorFilter($column, $shape, $operators, $sentinel));
    }

    /**
     * Membership in a JSON ARRAY column (`tags`), with an optional negation.
     *
     * `exact()` would compare the whole document and `partial()` would match a value
     * inside another one — see {@see JsonContainsOperatorFilter}.
     *
     * @param  list<RequestedOperator>  $operators
     */
    public static function jsonContains(
        string $name,
        ?string $internalName = null,
        array $operators = [RequestedOperator::Not],
    ): self {
        return new self($name, $internalName ?? $name, new JsonContainsOperatorFilter($operators));
    }

    /**
     * Map a filter to a model query scope. By default the value is passed as a
     * single scope argument. Pass `spread: true` only for a scope you own whose
     * arguments should come from a comma/array value — the request then controls
     * the argument count, so never enable it for a scope with optional
     * column/operator parameters.
     *
     * Pass `booleans: true` for a scope that takes a flag (`scopePublished(bool $published)`):
     * `true`/`false`/`1`/`0` then arrive as real booleans. Without it the scope receives
     * the text — and PHP reads the string `'false'` as truthy.
     */
    public static function scope(
        string $name,
        ?string $internalName = null,
        bool $spread = false,
        bool $booleans = false,
    ): self {
        return new self($name, $internalName ?? $name, new ScopeFilter($spread), $booleans);
    }

    /**
     * @param  Closure(Builder<Model>, mixed, string): void  $callback
     * @param  bool  $booleans  hand the callback `true`/`false`/`1`/`0` as real booleans
     *                          (other values stay text); off by default
     */
    public static function callback(
        string $name,
        Closure $callback,
        ?string $internalName = null,
        bool $booleans = false,
    ): self {
        return new self($name, $internalName ?? $name, new CallbackFilter($callback), $booleans);
    }

    public static function trashed(string $name = 'trashed', ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new TrashedFilter);
    }

    /**
     * @param  bool  $booleans  hand the filter `true`/`false`/`1`/`0` as real booleans
     *                          (other values stay text); off by default
     */
    public static function custom(string $name, Filter $filter, ?string $internalName = null, bool $booleans = false): self
    {
        return new self($name, $internalName ?? $name, $filter, $booleans);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value): void
    {
        $this->filter->apply(
            $query,
            $this->booleans ? $this->withBooleans($value) : $value,
            $this->internalName,
        );
    }

    /**
     * Each boolean spelling as a real boolean; every other value exactly as it was.
     */
    private function withBooleans(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->withBooleans(...), $value);
        }

        return is_string($value) && FilterValueShape::Boolean->matches($value)
            ? FilterValueShape::Boolean->cast($value)
            : $value;
    }
}
