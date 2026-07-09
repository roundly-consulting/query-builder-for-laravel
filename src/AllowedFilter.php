<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Enums\FilterOperator;
use RoundlyConsulting\QueryBuilder\Filters\CallbackFilter;
use RoundlyConsulting\QueryBuilder\Filters\ExactFilter;
use RoundlyConsulting\QueryBuilder\Filters\OperatorFilter;
use RoundlyConsulting\QueryBuilder\Filters\PartialFilter;
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

    public static function scope(string $name, ?string $internalName = null): self
    {
        return new self($name, $internalName ?? $name, new ScopeFilter);
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
