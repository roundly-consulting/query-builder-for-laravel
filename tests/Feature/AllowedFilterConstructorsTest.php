<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use RoundlyConsulting\QueryBuilder\AllowedFilter;
use RoundlyConsulting\QueryBuilder\Enums\FilterOperator;
use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Author;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

function builderFor(string $uri): QueryBuilder
{
    return QueryBuilder::for(Post::class, Request::create($uri));
}

beforeEach(function (): void {
    Post::create(['title' => 'Alpha', 'status' => 'published', 'views' => 30]);
    Post::create(['title' => 'Beta', 'status' => 'draft', 'views' => 10]);
});

it('builds a partial filter', function (): void {
    $result = builderFor('/?filter[title]=alph')
        ->allowedFilters(AllowedFilter::partial('title'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Alpha']);
});

it('builds a scope filter mapped to a model scope', function (): void {
    $result = builderFor('/?filter[status]=published')
        ->allowedFilters(AllowedFilter::scope('status'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Alpha']);
});

it('builds a callback filter', function (): void {
    $result = builderFor('/?filter[min_views]=20')
        ->allowedFilters(AllowedFilter::callback(
            'min_views',
            fn (Builder $query, mixed $value) => $query->where('views', '>=', $value),
        ))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Alpha']);
});

it('builds a trashed filter', function (): void {
    Post::create(['title' => 'Gone'])->delete();

    $result = builderFor('/?filter[trashed]=only')
        ->allowedFilters(AllowedFilter::trashed())
        ->get();

    expect($result->pluck('title')->all())->toBe(['Gone']);
});

it('builds an operator filter applying a fixed comparison', function (): void {
    $result = builderFor('/?filter[min_views]=20')
        ->allowedFilters(AllowedFilter::operator('min_views', FilterOperator::GreaterThanOrEqual, 'views'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Alpha']);
});

it('builds a begins-with filter', function (): void {
    $result = builderFor('/?filter[title]=Al')
        ->allowedFilters(AllowedFilter::beginsWith('title'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Alpha']);
});

it('builds an ends-with filter', function (): void {
    $result = builderFor('/?filter[title]=ta')
        ->allowedFilters(AllowedFilter::endsWith('title'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Beta']);
});

it('maps a filter name to a custom internal column', function (): void {
    $result = builderFor('/?filter[state]=draft')
        ->allowedFilters(AllowedFilter::exact('state', 'status'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Beta']);
});

it('builds a nullable filter with its sentinel and its negation', function (): void {
    Post::create(['title' => 'Orphan']);
    Post::where('title', 'Alpha')->update(['author_id' => 7]);

    $unset = builderFor('/?filter[author]=none')
        ->allowedFilters(AllowedFilter::nullable('author', 'author_id', FilterValueShape::Id))
        ->get();

    $set = builderFor('/?filter[author]=not:none')
        ->allowedFilters(AllowedFilter::nullable('author', 'author_id', FilterValueShape::Id))
        ->get();

    expect($unset->pluck('title')->sort()->values()->all())->toBe(['Beta', 'Orphan'])
        ->and($set->pluck('title')->all())->toBe(['Alpha']);
});

it('builds a relation filter whose negation excludes every related row', function (): void {
    $ada = Author::create(['name' => 'Ada']);
    Author::create(['name' => 'Cleo']);
    Post::where('title', 'Alpha')->update(['author_id' => $ada->id]);

    $result = QueryBuilder::for(Author::class, Request::create('/?filter[post]=not:Alpha'))
        ->allowedFilters(AllowedFilter::relation('post', 'posts', 'posts.title'))
        ->get();

    expect($result->pluck('name')->all())->toBe(['Cleo']);
});

it('answers a value of the wrong shape with an empty result rather than a driver error', function (): void {
    $result = builderFor('/?filter[views]=gt:garbage')
        ->allowedFilters(AllowedFilter::operators(
            'views',
            [RequestedOperator::GreaterThan],
            shape: FilterValueShape::Id,
        ))
        ->get();

    expect($result->pluck('title')->all())->toBe([]);
});

it('builds a search filter across several columns', function (): void {
    Post::create(['title' => 'Gamma', 'summary' => 'mentions alpha']);

    $filter = AllowedFilter::search('q', ['title', 'summary']);

    $result = builderFor('/?filter[q]=ALPH')->allowedFilters($filter)->get();

    expect($filter->name)->toBe('q')
        ->and($filter->internalName)->toBe('q')
        ->and($result->pluck('title')->sort()->values()->all())->toBe(['Alpha', 'Gamma']);
});
