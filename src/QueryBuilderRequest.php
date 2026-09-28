<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder;

use Illuminate\Http\Request;
use RoundlyConsulting\QueryBuilder\DataTransferObjects\SortParameter;
use RoundlyConsulting\QueryBuilder\Enums\SortDirection;

/**
 * Thin adapter over the framework Request that reads and normalises the frozen
 * filter/sort wire parameters once, so every strategy sees clean values.
 */
final class QueryBuilderRequest
{
    private function __construct(
        private readonly Request $request,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self($request);
    }

    /**
     * The allow-list keys requested in the filter bag, in request order.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return array_map(strval(...), array_keys($this->rawFilters()));
    }

    /**
     * The normalised filter values keyed by request name — a string, or a list of strings
     * for a comma/array value.
     *
     * @return array<string, string|list<string>>
     */
    public function filters(): array
    {
        $filters = [];

        foreach ($this->rawFilters() as $name => $value) {
            $filters[(string) $name] = $this->normalizeValue($value);
        }

        return $filters;
    }

    /**
     * The ordered sort parameters parsed from the sort string.
     *
     * @return list<SortParameter>
     */
    public function sorts(): array
    {
        $raw = $this->request->query($this->sortName());

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $sorts = [];
        $seen = [];

        foreach (explode(',', $raw) as $token) {
            if ($token === '') {
                continue;
            }

            $property = ltrim($token, '-');

            // Keep the first occurrence only: a repeated sort token would
            // otherwise append another ORDER BY for the same column.
            if (isset($seen[$property])) {
                continue;
            }

            $seen[$property] = true;

            $sorts[] = new SortParameter(
                property: $property,
                direction: SortDirection::fromToken($token),
            );

            if (count($sorts) >= $this->maxSorts()) {
                break;
            }
        }

        return $sorts;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function rawFilters(): array
    {
        $filters = $this->request->query($this->filterName());

        return is_array($filters) ? $filters : [];
    }

    /**
     * Normalise a single filter value: comma → list, empty string passthrough. One level
     * of array nesting is flattened.
     *
     * `true` / `false` stay TEXT here. Whether they are booleans depends on the column,
     * which only the filter knows — a title search for "false" is text, a flag column is
     * not — so the filters that hold booleans opt in ({@see AllowedFilter::boolean()},
     * `FilterValueShape::Boolean`, `booleans: true`) and every other filter sees the words
     * as sent.
     *
     * @return string|list<string>
     */
    private function normalizeValue(mixed $value): string|array
    {
        if (is_array($value)) {
            $flattened = [];

            foreach ($value as $item) {
                $normalized = $this->normalizeScalar(is_scalar($item) ? (string) $item : '');

                if (is_array($normalized)) {
                    foreach ($normalized as $part) {
                        $flattened[] = $part;
                    }

                    continue;
                }

                $flattened[] = $normalized;
            }

            return $this->capValues($flattened);
        }

        $normalized = $this->normalizeScalar(is_scalar($value) ? (string) $value : '');

        return is_array($normalized) ? $this->capValues($normalized) : $this->capScalar($normalized);
    }

    /**
     * Bound the request-driven work: cap the number of values and the length of
     * each so a cheap query string can't blow up into an expensive query.
     *
     * @param  list<string>  $values
     * @return list<string>
     */
    private function capValues(array $values): array
    {
        $capped = array_slice($values, 0, $this->maxFilterValues());

        return array_map($this->capScalar(...), $capped);
    }

    private function capScalar(string $value): string
    {
        return mb_substr($value, 0, $this->maxValueLength());
    }

    private function maxFilterValues(): int
    {
        return max(1, (int) config('query-builder.limits.max_filter_values', 50));
    }

    private function maxValueLength(): int
    {
        return max(1, (int) config('query-builder.limits.max_value_length', 255));
    }

    private function maxSorts(): int
    {
        return max(1, (int) config('query-builder.limits.max_sorts', 5));
    }

    /**
     * @return string|list<string>
     */
    private function normalizeScalar(string $value): string|array
    {
        return str_contains($value, ',') ? explode(',', $value) : $value;
    }

    private function filterName(): string
    {
        return (string) config('query-builder.parameters.filter', 'filter');
    }

    private function sortName(): string
    {
        return (string) config('query-builder.parameters.sort', 'sort');
    }
}
