<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\QueryBuilder\AllowedFilter;
use RoundlyConsulting\QueryBuilder\Enums\FilterOperator;
use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

/**
 * An empty element is NO value — `fixtures/wire-cases.json`: "empty elements are dropped",
 * "an empty parameter carries no value at all". A filter left with nothing adds no constraint.
 *
 * That is a different fact from "a value was given, but none of it fits the column", which
 * still matches nothing for a positive operator: dropping that filter would show the
 * unfiltered list, which reads as "the filter worked and everything matched".
 */
beforeEach(function (): void {
    Post::create(['title' => 'A', 'status' => 'draft', 'summary' => 'draft', 'views' => 1, 'active' => true]);
    Post::create(['title' => 'B', 'status' => 'archived', 'summary' => 'archived', 'views' => 2, 'active' => false]);
    Post::create(['title' => 'C', 'status' => 'published', 'summary' => 'published', 'views' => 3, 'active' => true]);
    // `summary` is NULL here, the row a `like '%%'` or `= ''` silently drops.
    Post::create(['title' => 'D', 'status' => 'published', 'views' => 4, 'active' => false]);
});

/**
 * @return array{0: list<string>, 1: list<mixed>}
 */
function emptyValueRun(string $uri, AllowedFilter $filter): array
{
    $builder = QueryBuilder::for(Post::class, Request::create($uri))->allowedFilters($filter);
    $bindings = $builder->getEloquentBuilder()->getBindings();

    /** @var list<string> $titles */
    $titles = $builder->get()->pluck('title')->sort()->values()->all();

    return [$titles, $bindings];
}

it('drops empty elements and adds no constraint when nothing is left', function (string $uri, AllowedFilter $filter, array $expected): void {
    [$titles, $bindings] = emptyValueRun($uri, $filter);

    expect($titles)->toBe($expected)
        ->and($bindings)->not->toContain('')
        ->and($bindings)->not->toContain('%%');
})->with([
    'partial, empty' => ['/?filter[summary]=', AllowedFilter::partial('summary'), ['A', 'B', 'C', 'D']],
    'partial, trailing comma' => ['/?filter[summary]=draft,', AllowedFilter::partial('summary'), ['A']],
    'exact, empty' => ['/?filter[status]=', AllowedFilter::exact('status'), ['A', 'B', 'C', 'D']],
    'exact, lone comma' => ['/?filter[status]=,', AllowedFilter::exact('status'), ['A', 'B', 'C', 'D']],
    'exact, double comma' => ['/?filter[status]=draft,,archived', AllowedFilter::exact('status'), ['A', 'B']],
    'operator, empty' => ['/?filter[summary]=', AllowedFilter::operator('summary', FilterOperator::Equal), ['A', 'B', 'C', 'D']],
    'operator, trailing comma' => ['/?filter[summary]=p,', AllowedFilter::operator('summary', FilterOperator::GreaterThanOrEqual), ['C']],
    'operators, empty' => ['/?filter[summary]=', AllowedFilter::operators('summary', [RequestedOperator::Not]), ['A', 'B', 'C', 'D']],
    'operators, double comma' => ['/?filter[status]=draft,,archived', AllowedFilter::operators('status', [RequestedOperator::Not]), ['A', 'B']],
    'operators, contains trailing comma' => ['/?filter[summary]='.urlencode('contains:draft,'), AllowedFilter::operators('summary', [RequestedOperator::Contains]), ['A']],
    'operators, contains nothing' => ['/?filter[summary]='.urlencode('contains:'), AllowedFilter::operators('summary', [RequestedOperator::Contains]), ['A', 'B', 'C', 'D']],
]);

it('adds no constraint for an empty value on a typed filter, but matches nothing for a misshapen one', function (string $uri, AllowedFilter $filter, array $expected): void {
    [$titles] = emptyValueRun($uri, $filter);

    expect($titles)->toBe($expected);
})->with([
    'boolean, empty' => ['/?filter[active]=', AllowedFilter::boolean('active'), ['A', 'B', 'C', 'D']],
    'boolean, lone comma' => ['/?filter[active]=,', AllowedFilter::boolean('active'), ['A', 'B', 'C', 'D']],
    'boolean, trailing comma' => ['/?filter[active]=true,', AllowedFilter::boolean('active'), ['A', 'C']],
    'boolean, misshapen' => ['/?filter[active]=maybe', AllowedFilter::boolean('active'), []],
    'id, empty' => ['/?filter[views]=', AllowedFilter::operators('views', [], shape: FilterValueShape::Id), ['A', 'B', 'C', 'D']],
    'id, misshapen' => ['/?filter[views]=garbage', AllowedFilter::operators('views', [], shape: FilterValueShape::Id), []],
    'id, negated empty' => ['/?filter[views]='.urlencode('not:'), AllowedFilter::operators('views', [RequestedOperator::Not], shape: FilterValueShape::Id), ['A', 'B', 'C', 'D']],
    'uuid, empty' => ['/?filter[status]=', AllowedFilter::operators('status', [], shape: FilterValueShape::Uuid), ['A', 'B', 'C', 'D']],
]);
