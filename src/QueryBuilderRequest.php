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
     * The normalised filter values keyed by request name.
     *
     * @return array<string, mixed>
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

        foreach (explode(',', $raw) as $token) {
            if ($token === '') {
                continue;
            }

            $sorts[] = new SortParameter(
                property: ltrim($token, '-'),
                direction: SortDirection::fromToken($token),
            );
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
     * Normalise a single filter value: comma → list, `true`/`false` → bool,
     * empty string passthrough. One level of array nesting is flattened.
     */
    private function normalizeValue(mixed $value): mixed
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

            return $flattened;
        }

        return $this->normalizeScalar(is_scalar($value) ? (string) $value : '');
    }

    /**
     * @return bool|string|list<string>
     */
    private function normalizeScalar(string $value): bool|string|array
    {
        $lower = strtolower($value);

        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if (str_contains($value, ',')) {
            return explode(',', $value);
        }

        return $value;
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
