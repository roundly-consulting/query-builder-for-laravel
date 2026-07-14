<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\QueryBuilder\QueryBuilderServiceProvider;

it('merges the package config with its defaults', function (): void {
    expect(config('query-builder.parameters.filter'))->toBe('filter')
        ->and(config('query-builder.parameters.sort'))->toBe('sort')
        ->and(config('query-builder.pagination.page_name'))->toBe('page')
        ->and(config('query-builder.pagination.per_page_name'))->toBe('per_page')
        ->and(config('query-builder.pagination.default_per_page'))->toBe(20)
        ->and(config('query-builder.pagination.max_per_page'))->toBe(100)
        ->and(config('query-builder.mode.unknown_filter'))->toBe('reject')
        ->and(config('query-builder.mode.unknown_sort'))->toBe('reject')
        ->and(config('query-builder.limits.max_filter_values'))->toBe(50)
        ->and(config('query-builder.limits.max_value_length'))->toBe(255)
        ->and(config('query-builder.limits.max_sorts'))->toBe(5);
});

it('publishes the config to the unchanged tag and path', function (): void {
    $paths = QueryBuilderServiceProvider::pathsToPublish(
        QueryBuilderServiceProvider::class,
        'query-builder-config',
    );

    expect($paths)->not->toBeEmpty()
        ->and(array_values($paths)[0])->toBe(config_path('query-builder.php'));
});

it('publishes the translations under the package tag', function (): void {
    $paths = QueryBuilderServiceProvider::pathsToPublish(
        QueryBuilderServiceProvider::class,
        'query-builder-translations',
    );

    expect($paths)->not->toBeEmpty()
        ->and(array_values($paths)[0])->toBe(app()->langPath('vendor/query-builder'));
});

it('resolves the translation namespace', function (): void {
    $message = trans('query-builder::errors.unknown_filter', ['unknown' => 'x', 'allowed' => 'y']);

    expect($message)->toContain('not allowed');
});

it('ships no migrations to load or publish', function (): void {
    expect(is_dir(dirname(__DIR__, 2).'/database/migrations'))->toBeFalse()
        ->and(QueryBuilderServiceProvider::pathsToPublish(
            QueryBuilderServiceProvider::class,
            'query-builder-migrations',
        ))->toBeEmpty();
});

it('reports the wire contract to artisan about', function (): void {
    config()->set('query-builder.pagination.page_name', 'p');
    config()->set('query-builder.mode.unknown_sort', 'ignore');

    Artisan::call('about', ['--only' => 'query-builder']);

    $output = Artisan::output();

    expect($output)->toContain('Filter parameter')
        ->and($output)->toContain('p / per_page')
        ->and($output)->toContain('20 default, 100 max')
        ->and($output)->toContain('REJECT')
        ->and($output)->toContain('IGNORE')
        ->and($output)->toContain('50 value(s), 255 char(s), 5 sort(s)');
});
