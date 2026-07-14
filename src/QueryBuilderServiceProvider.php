<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder;

use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

final class QueryBuilderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('query-builder')
            ->hasConfigFile()
            ->hasTranslations()
            ->contributesToAbout($this->aboutData(...));
    }

    /**
     * The `about` payload. Nothing here is a secret: this package's config *is*
     * the public wire contract a client already sends, so the parameter names,
     * bounds and modes render plainly.
     *
     * @return array<string, string>
     */
    private function aboutData(): array
    {
        return [
            'Filter parameter' => (string) config('query-builder.parameters.filter', 'filter'),
            'Sort parameter' => (string) config('query-builder.parameters.sort', 'sort'),
            'Page parameters' => sprintf(
                '%s / %s',
                (string) config('query-builder.pagination.page_name', 'page'),
                (string) config('query-builder.pagination.per_page_name', 'per_page'),
            ),
            'Page size' => sprintf(
                '%d default, %d max',
                (int) config('query-builder.pagination.default_per_page', 20),
                (int) config('query-builder.pagination.max_per_page', 100),
            ),
            'Unknown filter' => strtoupper((string) config('query-builder.mode.unknown_filter', 'reject')),
            'Unknown sort' => strtoupper((string) config('query-builder.mode.unknown_sort', 'reject')),
            'Request limits' => sprintf(
                '%d value(s), %d char(s), %d sort(s)',
                (int) config('query-builder.limits.max_filter_values', 50),
                (int) config('query-builder.limits.max_value_length', 255),
                (int) config('query-builder.limits.max_sorts', 5),
            ),
        ];
    }
}
