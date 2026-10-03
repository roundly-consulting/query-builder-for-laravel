<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownFilter;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\QueryBuilderRequest;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;
use RoundlyConsulting\QueryBuilder\Tests\Support\Requests\ListRequest;

/**
 * A typo in the host's query-builder config fails loudly. Before: a typo'd `mode.unknown_*`
 * silently became Reject, and the limits and page sizes were `(int)` cast and clamped, so
 * `max_sorts: 'five'` quietly became 1.
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

it('treats an unset mode as reject (strict config)', function (): void {
    config()->set('query-builder.mode.unknown_filter', null);

    expect(fn () => QueryBuilder::for(Post::class, Request::create('/?filter[bogus]=1'))->allowedFilters('status')->get())
        ->toThrow(UnknownFilter::class);
});

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
    'max_value_length blank' => ['query-builder.limits.max_value_length', '', 'filters'],
]);

it('reads canonical limit strings (strict config)', function (): void {
    config()->set('query-builder.limits.max_sorts', '2');

    expect(strictQbRequest('/')->maxSorts())->toBe(2);
});

it('refuses a junk page size, or a default above the cap (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    expect(fn () => ListRequest::create('/')->perPage())->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'default word' => ['query-builder.pagination.default_per_page', 'twenty'],
    'default zero' => ['query-builder.pagination.default_per_page', 0],
    'default above max' => ['query-builder.pagination.default_per_page', 500],
    'max decimal' => ['query-builder.pagination.max_per_page', '1.5'],
]);

it('refuses a blank or non-string parameter name (strict config)', function (string $key, mixed $value, Closure $read): void {
    config()->set($key, $value);

    expect($read)->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'filter blank' => ['query-builder.parameters.filter', '', fn () => strictQbRequest('/?filter[a]=1')->filters()],
    'sort array' => ['query-builder.parameters.sort', ['sort'], fn () => strictQbRequest('/?sort=a')->sorts()],
    'page name blank' => ['query-builder.pagination.page_name', ' ', fn () => ListRequest::create('/')->pageName()],
    'per page name int' => ['query-builder.pagination.per_page_name', 5, fn () => ListRequest::create('/')->perPage()],
]);

it('uses the defaults for unset parameter names and sizes (strict config)', function (): void {
    foreach (['parameters.filter', 'parameters.sort', 'pagination.page_name', 'pagination.per_page_name', 'pagination.default_per_page', 'pagination.max_per_page'] as $key) {
        config()->set("query-builder.{$key}", null);
    }

    expect(strictQbRequest('/?filter[a]=1&sort=b')->filters())->toBe(['a' => '1'])
        ->and(ListRequest::create('/')->pageName())->toBe('page')
        ->and(ListRequest::create('/?per_page=500')->perPage())->toBe(100)
        ->and(ListRequest::create('/')->perPage())->toBe(20);
});

it('refuses to render about over a junk limit (strict config)', function (): void {
    config()->set('query-builder.limits.max_sorts', 'five');

    expect(fn () => Artisan::call('about', ['--only' => 'query_builder']))
        ->toThrow(InvalidConfigurationException::class, 'query-builder.limits.max_sorts');
});
