<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RoundlyConsulting\QueryBuilder\DataTransferObjects\SortParameter;
use RoundlyConsulting\QueryBuilder\Enums\SortDirection;
use RoundlyConsulting\QueryBuilder\Enums\UnknownParameterMode;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownFilter;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownSort;
use RoundlyConsulting\QueryBuilder\Sorts\FieldSort;

/**
 * @mixin EloquentBuilder<Model>
 */
final class QueryBuilder
{
    /** @var array<string, AllowedFilter> */
    private array $allowedFilters = [];

    /** @var array<string, AllowedSort> */
    private array $allowedSorts = [];

    private ?string $defaultSort = null;

    private bool $applied = false;

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
        $this->defaultSort = $sort;

        return $this;
    }

    /**
     * @return EloquentBuilder<Model>
     */
    public function getEloquentBuilder(): EloquentBuilder
    {
        $this->applyOnce();

        return $this->subject;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->applyOnce();

        $result = $this->subject->{$name}(...$arguments);

        return $result instanceof BuilderContract ? $this : $result;
    }

    private function applyOnce(): void
    {
        if ($this->applied) {
            return;
        }

        $this->applied = true;

        $this->applyFilters();
        $this->applySorts();
    }

    private function applyFilters(): void
    {
        $requested = $this->request->filters();

        $unknown = array_values(array_diff(
            $this->request->filterKeys(),
            array_keys($this->allowedFilters),
        ));

        if ($unknown !== [] && $this->mode('unknown_filter') === UnknownParameterMode::Reject) {
            throw UnknownFilter::make($unknown, array_keys($this->allowedFilters));
        }

        foreach ($this->allowedFilters as $name => $filter) {
            if (array_key_exists($name, $requested)) {
                $filter->apply($this->subject, $requested[$name]);
            }
        }
    }

    private function applySorts(): void
    {
        $sorts = $this->request->sorts();

        if ($sorts === []) {
            $this->applyDefaultSort();

            return;
        }

        $unknown = array_values(array_diff(
            array_map(static fn (SortParameter $sort): string => $sort->property, $sorts),
            array_keys($this->allowedSorts),
        ));

        if ($unknown !== [] && $this->mode('unknown_sort') === UnknownParameterMode::Reject) {
            throw UnknownSort::make($unknown, array_keys($this->allowedSorts));
        }

        foreach ($sorts as $sort) {
            $allowed = $this->allowedSorts[$sort->property] ?? null;

            $allowed?->apply($this->subject, $sort->direction);
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

    private function mode(string $key): UnknownParameterMode
    {
        return UnknownParameterMode::from(
            (string) config("query-builder.mode.{$key}", 'reject'),
        );
    }
}
