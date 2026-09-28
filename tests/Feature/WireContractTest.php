<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\QueryBuilder\AllowedFilter;
use RoundlyConsulting\QueryBuilder\AllowedSort;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\QueryBuilderRequest;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

/**
 * Pins the frozen HTTP wire semantics so a future refactor cannot silently move
 * the platform contract. Every case exercises the accepted request format only.
 */
function wire(string $uri): QueryBuilder
{
    return QueryBuilder::for(Post::class, Request::create($uri));
}

// B1 — empty partial value ("%%") matches every row (a no-op filter).
it('treats an empty partial value as a match-all no-op', function (): void {
    Post::create(['title' => 'Alpha']);
    Post::create(['title' => 'Beta']);

    $result = wire('/?filter[title]=')
        ->allowedFilters(AllowedFilter::partial('title'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Alpha', 'Beta']);
});

// B2 — a boolean filter casts to a real bool; every other filter keeps the text;
// a comma list becomes whereIn.
it('casts a boolean filter value rather than matching the string', function (): void {
    Post::create(['title' => 'On', 'active' => true]);
    Post::create(['title' => 'Off', 'active' => false]);

    $result = wire('/?filter[active]=false')
        ->allowedFilters(AllowedFilter::boolean('active'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Off']);
});

it('keeps an exact text value of false as text', function (): void {
    Post::create(['title' => 'Literal', 'status' => 'false']);
    Post::create(['title' => 'Other', 'status' => 'draft']);

    $result = wire('/?filter[status]=false')
        ->allowedFilters(AllowedFilter::exact('status'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Literal']);
});

it('turns a comma exact list into a whereIn', function (): void {
    Post::create(['title' => 'A', 'status' => 'published']);
    Post::create(['title' => 'B', 'status' => 'draft']);
    Post::create(['title' => 'C', 'status' => 'archived']);

    $result = wire('/?filter[status]=published,archived')
        ->allowedFilters(AllowedFilter::exact('status'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['A', 'C']);
});

// B3 — scope arg spread is opt-in: with spread:true a comma list spreads across
// the scope arguments; by default it arrives as a single array argument.
it('spreads a comma scope value across scope arguments when opted in', function (): void {
    Post::create(['title' => 'Small', 'views' => 3]);
    Post::create(['title' => 'Mid', 'views' => 30]);
    Post::create(['title' => 'Big', 'views' => 300]);

    $result = wire('/?filter[views_between]=10,100')
        ->allowedFilters(AllowedFilter::scope('views_between', spread: true))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Mid']);
});

it('passes a comma scope value as one argument by default', function (): void {
    Post::create(['title' => 'A']);
    Post::create(['title' => 'B']);
    Post::create(['title' => 'C']);

    $result = wire('/?filter[titles]=A,C')
        ->allowedFilters(AllowedFilter::scope('titles'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['A', 'C']);
});

it('passes a scalar bool scope value as a single argument when opted in', function (): void {
    Post::create(['title' => 'Live', 'status' => 'published']);
    Post::create(['title' => 'Hidden', 'status' => 'draft']);

    $live = wire('/?filter[published]=true')
        ->allowedFilters(AllowedFilter::scope('published', booleans: true))
        ->get();
    $hidden = wire('/?filter[published]=false')
        ->allowedFilters(AllowedFilter::scope('published', booleans: true))
        ->get();

    expect($live->pluck('title')->all())->toBe(['Live'])
        ->and($hidden->pluck('title')->all())->toBe(['Hidden']);
});

// B4 — multi-sort applies left→right.
it('applies a comma multi-sort left to right', function (): void {
    Post::create(['title' => 'A', 'status' => 'published', 'views' => 10]);
    Post::create(['title' => 'B', 'status' => 'published', 'views' => 20]);
    Post::create(['title' => 'C', 'status' => 'draft', 'views' => 30]);

    $result = wire('/?sort=status,-views')
        ->allowedSorts('status', 'views')
        ->get();

    expect($result->pluck('title')->all())->toBe(['C', 'B', 'A']);
});

// B5 — ignore mode drops the unknown key and applies only the allowed ones.
it('applies allowed filters while ignoring unknown ones in ignore mode', function (): void {
    config()->set('query-builder.mode.unknown_filter', 'ignore');
    Post::create(['title' => 'Keep', 'status' => 'published']);
    Post::create(['title' => 'Drop', 'status' => 'draft']);

    $result = wire('/?filter[status]=published&filter[bogus]=1')
        ->allowedFilters('status')
        ->get();

    expect($result->pluck('title')->all())->toBe(['Keep']);
});

// B6 — one-level filter[x][]= flattening; deeper nesting collapses to a flat list.
it('flattens one level of filter array nesting', function (): void {
    $filters = QueryBuilderRequest::fromRequest(
        Request::create('/?filter[status][]=a&filter[status][]=b'),
    )->filters();

    expect($filters)->toBe(['status' => ['a', 'b']]);
});

it('collapses deeper filter nesting to a flat scalar list', function (): void {
    $filters = QueryBuilderRequest::fromRequest(
        Request::create('/?filter[status][x]=z'),
    )->filters();

    expect($filters)->toBe(['status' => ['z']]);
});

// B7 — public→internal name mapping for both filters and sorts.
it('maps public filter and sort names to internal columns', function (): void {
    Post::create(['title' => 'A', 'status' => 'published', 'views' => 30]);
    Post::create(['title' => 'B', 'status' => 'published', 'views' => 10]);
    Post::create(['title' => 'C', 'status' => 'draft', 'views' => 20]);

    $result = wire('/?filter[state]=published&sort=-popularity')
        ->allowedFilters(AllowedFilter::exact('state', 'status'))
        ->allowedSorts(AllowedSort::field('popularity', 'views'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['A', 'B']);
});

// B8 — filters/sorts apply exactly once across repeated forwarded calls.
it('applies filters and sorts once across chained forwarded calls', function (): void {
    Post::create(['title' => 'A', 'status' => 'published', 'views' => 30]);
    Post::create(['title' => 'B', 'status' => 'draft', 'views' => 10]);

    $builder = wire('/?filter[status]=published&sort=-views')
        ->allowedFilters('status')
        ->allowedSorts('views');

    $builder->where('views', '>', 0);
    $eloquent = $builder->getEloquentBuilder();

    expect($eloquent->getQuery()->wheres)->toHaveCount(2)
        ->and($eloquent->getQuery()->orders)->toHaveCount(1);
});
