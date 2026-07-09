<?php

declare(strict_types=1);

use Illuminate\Http\Request;
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

it('casts true and false strings to booleans', function (): void {
    $request = qbRequest('/?filter[active]=true&filter[archived]=FALSE');

    expect($request->filters())->toBe(['active' => true, 'archived' => false]);
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

it('honours custom configured parameter names', function (): void {
    config()->set('query-builder.parameters.filter', 'where');
    config()->set('query-builder.parameters.sort', 'order');

    $request = qbRequest('/?where[status]=a&order=-name');

    expect($request->filters())->toBe(['status' => 'a'])
        ->and($request->sorts()[0]->property)->toBe('name')
        ->and($request->sorts()[0]->direction)->toBe(SortDirection::Descending);
});
