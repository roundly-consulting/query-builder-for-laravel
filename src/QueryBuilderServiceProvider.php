<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder;

use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\QueryBuilder\Support\QueryBuilderConfig;

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
            'Filter parameter' => QueryBuilderConfig::filterParameter(),
            'Sort parameter' => QueryBuilderConfig::sortParameter(),
            'Page parameters' => sprintf('%s / %s', QueryBuilderConfig::pageName(), QueryBuilderConfig::perPageName()),
            'Page size' => sprintf('%d default, %d max', QueryBuilderConfig::defaultPerPage(), QueryBuilderConfig::maxPerPage()),
            'Unknown filter' => strtoupper(QueryBuilderConfig::unknownFilterMode()->value),
            'Unknown sort' => strtoupper(QueryBuilderConfig::unknownSortMode()->value),
            'Request limits' => sprintf(
                '%d value(s), %d char(s), %d sort(s)',
                QueryBuilderConfig::maxFilterValues(),
                QueryBuilderConfig::maxValueLength(),
                QueryBuilderConfig::maxSorts(),
            ),
        ];
    }
}
