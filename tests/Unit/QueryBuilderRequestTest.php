<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\QueryBuilder\Enums\SortDirection;
use RoundlyConsulting\QueryBuilder\QueryBuilderRequest;

function qbRequest(string $uri): QueryBuilderRequest
{
    return QueryBuilderRequest::fromRequest(Request::create($uri));
}

it('comma-splits multi-value filters into a list', function (): void {
    $request = qbRequest('/?filter[status]=a,b,c');

    expect($request->filters())->toBe(['status' => ['a', 'b', 'c']]);
});

it('keeps a single filter value scalar', function (): void {
    expect(qbRequest('/?filter[status]=draft')->filters())->toBe(['status' => 'draft']);
});

it('keeps true and false as the text that was sent', function (): void {
    // Whether they are booleans depends on the column, which only the filter knows.
    $request = qbRequest('/?filter[active]=true&filter[archived]=FALSE&filter[flags]=true,false');

    expect($request->filters())->toBe(['active' => 'true', 'archived' => 'FALSE', 'flags' => ['true', 'false']]);
});

it('passes an empty filter value through unchanged', function (): void {
    expect(qbRequest('/?filter[status]=')->filters())->toBe(['status' => '']);
});

it('returns empty arrays when no filter or sort bag is present', function (): void {
    $request = qbRequest('/');

    expect($request->filters())->toBe([])
        ->and($request->sorts())->toBe([])
        ->and($request->filterKeys())->toBe([]);
});

it('flattens one level of array nesting', function (): void {
    $request = qbRequest('/?filter[status][]=a&filter[status][]=b,c');

    expect($request->filters())->toBe(['status' => ['a', 'b', 'c']]);
});

it('exposes the requested filter keys', function (): void {
    expect(qbRequest('/?filter[status]=a&filter[views]=1')->filterKeys())
        ->toBe(['status', 'views']);
});

it('parses ordered sort parameters with direction', function (): void {
    $sorts = qbRequest('/?sort=-created_at,name')->sorts();

    expect($sorts)->toHaveCount(2)
        ->and($sorts[0]->property)->toBe('created_at')
        ->and($sorts[0]->direction)->toBe(SortDirection::Descending)
        ->and($sorts[1]->property)->toBe('name')
        ->and($sorts[1]->direction)->toBe(SortDirection::Ascending);
});

it('skips empty tokens in the sort string', function (): void {
    $sorts = qbRequest('/?sort=name,')->sorts();

    expect($sorts)->toHaveCount(1)
        ->and($sorts[0]->property)->toBe('name');
});

it('caps the number of comma-separated filter values', function (): void {
    config()->set('query-builder.limits.max_filter_values', 3);

    $request = qbRequest('/?filter[status]=a,b,c,d,e');

    expect($request->filters())->toBe(['status' => ['a', 'b', 'c']]);
});

it('caps the number of array filter values', function (): void {
    config()->set('query-builder.limits.max_filter_values', 2);

    $request = qbRequest('/?filter[id][]=1&filter[id][]=2&filter[id][]=3');

    expect($request->filters())->toBe(['id' => ['1', '2']]);
});

it('caps the length of a scalar filter value', function (): void {
    config()->set('query-builder.limits.max_value_length', 5);

    expect(qbRequest('/?filter[title]=abcdefghij')->filters())
        ->toBe(['title' => 'abcde']);
});

it('caps the length of each value in a list', function (): void {
    config()->set('query-builder.limits.max_value_length', 3);

    expect(qbRequest('/?filter[title]=abcdef,ghijkl')->filters())
        ->toBe(['title' => ['abc', 'ghi']]);
});

it('leaves the sort cap to the builder, which alone knows which sorts count', function (): void {
    config()->set('query-builder.limits.max_sorts', 2);

    $request = qbRequest('/?sort=a,b,c,d');

    expect(array_map(static fn ($sort): string => $sort->property, $request->sorts()))
        ->toBe(['a', 'b', 'c', 'd'])
        ->and($request->maxSorts())->toBe(2);
});

it('throws for a sort cap below one instead of clamping it (strict config)', function (): void {
    config()->set('query-builder.limits.max_sorts', 0);

    expect(fn () => qbRequest('/')->maxSorts())
        ->toThrow(InvalidConfigurationException::class, 'query-builder.limits.max_sorts');
});

it('deduplicates repeated sort tokens keeping the first occurrence', function (): void {
    $sorts = qbRequest('/?sort=views,-views,name,views')->sorts();

    expect($sorts)->toHaveCount(2)
        ->and($sorts[0]->property)->toBe('views')
        ->and($sorts[0]->direction)->toBe(SortDirection::Ascending)
        ->and($sorts[1]->property)->toBe('name');
});

it('honours custom configured parameter names', function (): void {
    config()->set('query-builder.parameters.filter', 'where');
    config()->set('query-builder.parameters.sort', 'order');

    $request = qbRequest('/?where[status]=a&order=-name');

    expect($request->filters())->toBe(['status' => 'a'])
        ->and($request->sorts()[0]->property)->toBe('name')
        ->and($request->sorts()[0]->direction)->toBe(SortDirection::Descending);
});
