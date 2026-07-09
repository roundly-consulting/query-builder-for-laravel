<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\QueryBuilderServiceProvider;

it('merges the package config with its defaults', function (): void {
    expect(config('query-builder.parameters.filter'))->toBe('filter')
        ->and(config('query-builder.parameters.sort'))->toBe('sort')
        ->and(config('query-builder.pagination.default_per_page'))->toBe(20)
        ->and(config('query-builder.pagination.max_per_page'))->toBe(100)
        ->and(config('query-builder.mode.unknown_filter'))->toBe('reject')
        ->and(config('query-builder.mode.unknown_sort'))->toBe('reject');
});

it('registers the config publish tag', function (): void {
    $paths = QueryBuilderServiceProvider::pathsToPublish(
        QueryBuilderServiceProvider::class,
        'query-builder-config',
    );

    expect($paths)->not->toBeEmpty();
});

it('resolves the translation namespace', function (): void {
    $message = trans('query-builder::errors.unknown_filter', ['unknown' => 'x', 'allowed' => 'y']);

    expect($message)->toContain('not allowed');
});
