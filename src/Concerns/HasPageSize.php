<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Concerns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mix into a FormRequest to validate and resolve the `per_page` page size.
 * Fold pageSizeRules() into rules() and read the resolved size via perPage().
 *
 * @mixin FormRequest
 */
trait HasPageSize
{
    /**
     * Validation rules for the page-size parameter — merge into rules().
     *
     * @return array<string, list<string>>
     */
    public function pageSizeRules(): array
    {
        $max = $this->maxPerPage();

        return [
            $this->perPageName() => ['nullable', 'integer', 'min:1', "max:{$max}"],
        ];
    }

    /**
     * The resolved page size: the requested value (hard-capped at the max as
     * defence in depth), or the configured default when absent or invalid.
     */
    public function perPage(): int
    {
        $value = $this->integer($this->perPageName());

        if ($value < 1) {
            return $this->defaultPerPage();
        }

        return min($value, $this->maxPerPage());
    }

    private function perPageName(): string
    {
        return (string) config('query-builder.pagination.per_page_name', 'per_page');
    }

    private function defaultPerPage(): int
    {
        return (int) config('query-builder.pagination.default_per_page', 20);
    }

    private function maxPerPage(): int
    {
        return (int) config('query-builder.pagination.max_per_page', 100);
    }
}
