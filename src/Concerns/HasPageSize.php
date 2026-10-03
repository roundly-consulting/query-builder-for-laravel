<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use RoundlyConsulting\QueryBuilder\Support\QueryBuilderConfig;

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

    /**
     * The configured paginator page parameter name — pass it to a paginator you
     * build by hand (`->paginate($request->perPage(), pageName: $request->pageName())`).
     * `QueryBuilder` applies it for you.
     */
    public function pageName(): string
    {
        return QueryBuilderConfig::pageName();
    }

    private function perPageName(): string
    {
        return QueryBuilderConfig::perPageName();
    }

    private function defaultPerPage(): int
    {
        return QueryBuilderConfig::defaultPerPage();
    }

    private function maxPerPage(): int
    {
        return QueryBuilderConfig::maxPerPage();
    }
}
