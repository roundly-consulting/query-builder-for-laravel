<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RoundlyConsulting\QueryBuilder\Enums\SortDirection;
use RoundlyConsulting\QueryBuilder\Enums\UnknownParameterMode;
use RoundlyConsulting\QueryBuilder\Exceptions\AllowListAlreadyApplied;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownFilter;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownSort;
use RoundlyConsulting\QueryBuilder\Sorts\FieldSort;

/**
 * Declare the allow-list first, then use it like any Eloquent builder.
 *
 * The request is applied the first time a call is forwarded to the underlying builder
 * (`where()`, `with()`, `get()`, …), because a forwarded call may be the one that runs the
 * query. `allowedFilters()`, `allowedSorts()` and `defaultSort()` after that point throw
 * {@see AllowListAlreadyApplied} rather than being silently ignored.
 *
 * @mixin EloquentBuilder<Model>
 */
final class QueryBuilder
{
    /** @var array<string, AllowedFilter> */
    private array $allowedFilters = [];

    /** @var array<string, AllowedSort> */
    private array $allowedSorts = [];

    private ?string $defaultSort = null;

    /** The forwarded call that applied the request, or `null` while it is still pending. */
    private ?string $appliedBy = null;

    /**
     * @param  EloquentBuilder<Model>  $subject
     */
    private function __construct(
        private readonly EloquentBuilder $subject,
        private readonly QueryBuilderRequest $request,
    ) {}

    /**
     * @param  class-string<Model>|EloquentBuilder<Model>  $subject
     */
    public static function for(string|EloquentBuilder $subject, ?Request $request = null): self
    {
        $builder = is_string($subject) ? $subject::query() : $subject;

        return new self($builder, QueryBuilderRequest::fromRequest($request ?? request()));
    }

    /**
     * @return $this
     */
    public function allowedFilters(AllowedFilter|string ...$filters): self
    {
        $this->ensureNotApplied(__FUNCTION__);

        foreach ($filters as $filter) {
            $filter = is_string($filter) ? AllowedFilter::exact($filter) : $filter;

            $this->allowedFilters[$filter->name] = $filter;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function allowedSorts(AllowedSort|string ...$sorts): self
    {
        $this->ensureNotApplied(__FUNCTION__);

        foreach ($sorts as $sort) {
            $sort = is_string($sort) ? AllowedSort::field($sort) : $sort;

            $this->allowedSorts[$sort->name] = $sort;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function defaultSort(string $sort): self
    {
        $this->ensureNotApplied(__FUNCTION__);

        $this->defaultSort = $sort;

        return $this;
    }

    /**
     * @return EloquentBuilder<Model>
     */
    public function getEloquentBuilder(): EloquentBuilder
    {
        $this->applyOnce(__FUNCTION__);

        return $this->subject;
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->applyOnce($name);

        if ($name === 'paginate' || $name === 'simplePaginate') {
            $arguments = $this->withConfiguredPageName($arguments);
        }

        $result = $this->subject->{$name}(...$arguments);

        return $result instanceof BuilderContract ? $this : $result;
    }

    /**
     * Hand the paginator the configured page parameter name, so a host that
     * renames it in config gets it honoured on the wire (links, `?page=`) —
     * the shipped default is Laravel's own `page`, so nothing changes unless it
     * is configured. A caller that names the page itself still wins.
     *
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    private function withConfiguredPageName(array $arguments): array
    {
        // `paginate($perPage, $columns, $pageName, $page)` — a third positional
        // argument is already the page name.
        if (array_key_exists('pageName', $arguments) || array_key_exists(2, $arguments)) {
            return $arguments;
        }

        $arguments['pageName'] = (string) config('query-builder.pagination.page_name', 'page');

        return $arguments;
    }

    private function applyOnce(string $by): void
    {
        if ($this->appliedBy !== null) {
            return;
        }

        $this->appliedBy = $by;

        $this->applyFilters();
        $this->applySorts();
    }

    /**
     * Refuse a declaration the request can no longer honour — see {@see AllowListAlreadyApplied}.
     */
    private function ensureNotApplied(string $declaration): void
    {
        if ($this->appliedBy !== null) {
            throw AllowListAlreadyApplied::make($declaration, $this->appliedBy);
        }
    }

    private function applyFilters(): void
    {
        $requested = $this->request->filters();

        $unknown = array_values(array_diff(
            $this->request->filterKeys(),
            array_keys($this->allowedFilters),
        ));

        if ($unknown !== [] && $this->mode('query-builder.mode.unknown_filter') === UnknownParameterMode::Reject) {
            throw UnknownFilter::make($unknown, array_keys($this->allowedFilters));
        }

        foreach ($this->allowedFilters as $name => $filter) {
            if (array_key_exists($name, $requested)) {
                $filter->apply($this->subject, $requested[$name]);
            }
        }
    }

    /**
     * Apply the requested sorts the allow-list knows, left to right, at most
     * `limits.max_sorts` of them.
     *
     * Only an allow-listed sort takes a place under the cap, so in `ignore` mode an unknown
     * token cannot crowd out a valid one behind it. Tokens past the cap are never looked at
     * — in `reject` mode too, exactly as the cap has always read. When nothing requested
     * survives (no `sort`, or every token ignored) the default sort applies: an ignored sort
     * reads as if it were absent, rather than handing pages back in whatever order the
     * database picks.
     */
    private function applySorts(): void
    {
        $applicable = [];
        $unknown = [];

        foreach ($this->request->sorts() as $sort) {
            $allowed = $this->allowedSorts[$sort->property] ?? null;

            if ($allowed === null) {
                $unknown[] = $sort->property;

                continue;
            }

            $applicable[] = [$allowed, $sort];

            if (count($applicable) >= $this->request->maxSorts()) {
                break;
            }
        }

        if ($unknown !== [] && $this->mode('query-builder.mode.unknown_sort') === UnknownParameterMode::Reject) {
            throw UnknownSort::make($unknown, array_keys($this->allowedSorts));
        }

        if ($applicable === []) {
            $this->applyDefaultSort();

            return;
        }

        foreach ($applicable as [$allowed, $sort]) {
            $allowed->apply($this->subject, $sort->direction);
        }
    }

    private function applyDefaultSort(): void
    {
        if ($this->defaultSort === null) {
            return;
        }

        foreach (explode(',', $this->defaultSort) as $token) {
            if ($token === '') {
                continue;
            }

            $property = ltrim($token, '-');
            $direction = SortDirection::fromToken($token);

            $allowed = $this->allowedSorts[$property] ?? null;

            if ($allowed !== null) {
                $allowed->apply($this->subject, $direction);

                continue;
            }

            (new FieldSort)->apply($this->subject, $direction, $property);
        }
    }

    /**
     * The mode configured under a full config key (passed whole, so every key
     * this package reads is a real string literal a config audit can find).
     */
    private function mode(string $key): UnknownParameterMode
    {
        // Fail closed: an invalid/typo'd config value falls back to the secure
        // Reject default rather than throwing a ValueError (uncaught 500).
        return UnknownParameterMode::tryFrom(
            (string) config($key, 'reject'),
        ) ?? UnknownParameterMode::Reject;
    }
}
