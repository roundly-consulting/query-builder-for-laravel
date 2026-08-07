<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Enums\FilterOperator;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\Filters\CallbackFilter;
use RoundlyConsulting\QueryBuilder\Filters\ExactFilter;
use RoundlyConsulting\QueryBuilder\Filters\OperatorFilter;
use RoundlyConsulting\QueryBuilder\Filters\PartialFilter;
use RoundlyConsulting\QueryBuilder\Filters\RequestedOperatorFilter;
use RoundlyConsulting\QueryBuilder\Filters\ScopeFilter;
use RoundlyConsulting\QueryBuilder\Filters\TrashedFilter;

final class AllowedFilter
{
    private function __construct(
        public readonly string $name,
        public readonly string $internalName,
        private readonly Filter $filter,
    ) {}

    public static function exact(string $name, ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new ExactFilter);
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
     */
    public static function operators(
        string $name,
        array $operators,
        ?string $internalName = null,
        bool $partialByDefault = false,
    ): self {
        return new self(
            $name,
            $internalName ?? $name,
            new RequestedOperatorFilter($operators, $partialByDefault),
        );
    }

    /**
     * Map a filter to a model query scope. By default the value is passed as a
     * single scope argument. Pass `spread: true` only for a scope you own whose
     * arguments should come from a comma/array value — the request then controls
     * the argument count, so never enable it for a scope with optional
     * column/operator parameters.
     */
    public static function scope(string $name, ?string $internalName = null, bool $spread = false): self
    {
        return new self($name, $internalName ?? $name, new ScopeFilter($spread));
    }

    /**
     * @param  Closure(Builder<Model>, mixed, string): void  $callback
     */
    public static function callback(string $name, Closure $callback, ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new CallbackFilter($callback));
    }

    public static function trashed(string $name = 'trashed', ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new TrashedFilter);
    }

    public static function custom(string $name, Filter $filter, ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, $filter);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value): void
    {
        $this->filter->apply($query, $value, $this->internalName);
    }
}
