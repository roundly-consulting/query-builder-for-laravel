<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownFilter;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\QueryBuilderRequest;
use RoundlyConsulting\QueryBuilder\QueryBuilderServiceProvider;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;
use RoundlyConsulting\QueryBuilder\Tests\Support\Requests\ListRequest;

/**
 * A typo in the host's query-builder config fails loudly. Before: a typo'd `mode.unknown_*`
 * silently became Reject, and the limits and page sizes were `(int)` cast and clamped, so
 * `max_sorts: 'five'` quietly became 1. A blank value (a host's `KEY=`) is not set and takes
 * the default.
 */
function strictQbRequest(string $uri): QueryBuilderRequest
{
    return QueryBuilderRequest::fromRequest(Request::create($uri));
}

it('refuses a typo in an unknown-parameter mode instead of rejecting (strict config)', function (string $key, string $uri): void {
    config()->set($key, 'ignroe');

    expect(fn () => QueryBuilder::for(Post::class, Request::create($uri))->allowedFilters('status')->allowedSorts('title')->get())
        ->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'filter' => ['query-builder.mode.unknown_filter', '/?filter[bogus]=1'],
    'sort' => ['query-builder.mode.unknown_sort', '/?sort=-bogus'],
]);

it('treats an unset or blank mode as reject (strict config)', function (?string $unset): void {
    config()->set('query-builder.mode.unknown_filter', $unset);

    expect(fn () => QueryBuilder::for(Post::class, Request::create('/?filter[bogus]=1'))->allowedFilters('status')->get())
        ->toThrow(UnknownFilter::class);
})->with(['absent' => null, 'empty' => '', 'whitespace' => ' ']);

it('refuses a junk or non-positive limit instead of clamping it (strict config)', function (string $key, mixed $value, string $method): void {
    config()->set($key, $value);

    $request = strictQbRequest('/?filter[status]=a,b&sort=title');

    expect(fn () => match ($method) {
        'maxSorts' => $request->maxSorts(),
        'filters' => $request->filters(),
    })->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'max_sorts word' => ['query-builder.limits.max_sorts', 'five', 'maxSorts'],
    'max_sorts zero' => ['query-builder.limits.max_sorts', 0, 'maxSorts'],
    'max_filter_values decimal' => ['query-builder.limits.max_filter_values', '2.5', 'filters'],
    'max_value_length word' => ['query-builder.limits.max_value_length', 'long', 'filters'],
]);

it('reads canonical limit strings (strict config)', function (): void {
    config()->set('query-builder.limits.max_sorts', '2');

    expect(strictQbRequest('/')->maxSorts())->toBe(2);
});

it('reads a blank limit as not set, taking the default (strict config)', function (string $blank): void {
    config()->set('query-builder.limits.max_sorts', $blank);
    config()->set('query-builder.limits.max_filter_values', $blank);
    config()->set('query-builder.limits.max_value_length', $blank);

    expect(strictQbRequest('/')->maxSorts())->toBe(5)
        ->and(strictQbRequest('/?filter[status]=a,b')->filters())->toBe(['status' => ['a', 'b']]);
})->with(['empty' => '', 'whitespace' => '  ']);

it('refuses a junk page size, or a default above the cap (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    expect(fn () => ListRequest::create('/')->perPage())->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'default word' => ['query-builder.pagination.default_per_page', 'twenty'],
    'default zero' => ['query-builder.pagination.default_per_page', 0],
    'default above max' => ['query-builder.pagination.default_per_page', 500],
    'max decimal' => ['query-builder.pagination.max_per_page', '1.5'],
]);

it('caps an unset default page size at a lower max instead of throwing (strict config)', function (Closure $configure): void {
    $configure();

    Artisan::call('about', ['--only' => 'query-builder']);

    expect(ListRequest::create('/')->perPage())->toBe(10)
        ->and(Artisan::output())->toContain('10 default, 10 max');
})->with([
    // A host `pagination` block replaces the package's whole block — `mergeConfigFrom`
    // merges top-level keys only — so `default_per_page` is simply absent.
    'absent' => [function (): void {
        config()->set('query-builder', ['pagination' => ['max_per_page' => 10]]);
        (new QueryBuilderServiceProvider(app()))->register();
    }],
    'null' => [fn () => config()->set([
        'query-builder.pagination.default_per_page' => null,
        'query-builder.pagination.max_per_page' => 10,
    ])],
    'blank' => [fn () => config()->set([
        'query-builder.pagination.default_per_page' => ' ',
        'query-builder.pagination.max_per_page' => 10,
    ])],
]);

it('refuses a non-string parameter name (strict config)', function (string $key, mixed $value, Closure $read): void {
    config()->set($key, $value);

    expect($read)->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'filter bool' => ['query-builder.parameters.filter', false, fn () => strictQbRequest('/?filter[a]=1')->filters()],
    'sort array' => ['query-builder.parameters.sort', ['sort'], fn () => strictQbRequest('/?sort=a')->sorts()],
    'page name array' => ['query-builder.pagination.page_name', ['page'], fn () => ListRequest::create('/')->pageName()],
    'per page name int' => ['query-builder.pagination.per_page_name', 5, fn () => ListRequest::create('/')->perPage()],
]);

it('uses the defaults for unset or blank parameter names and sizes (strict config)', function (?string $unset): void {
    foreach (['parameters.filter', 'parameters.sort', 'pagination.page_name', 'pagination.per_page_name', 'pagination.default_per_page', 'pagination.max_per_page'] as $key) {
        config()->set("query-builder.{$key}", $unset);
    }

    expect(strictQbRequest('/?filter[a]=1&sort=b')->filters())->toBe(['a' => '1'])
        ->and(ListRequest::create('/')->pageName())->toBe('page')
        ->and(ListRequest::create('/?per_page=500')->perPage())->toBe(100)
        ->and(ListRequest::create('/')->perPage())->toBe(20);
})->with(['absent' => null, 'empty' => '', 'whitespace' => ' ']);

it('refuses to render about over a junk limit (strict config)', function (): void {
    config()->set('query-builder.limits.max_sorts', 'five');

    expect(fn () => Artisan::call('about', ['--only' => 'query_builder']))
        ->toThrow(InvalidConfigurationException::class, 'query-builder.limits.max_sorts');
});
