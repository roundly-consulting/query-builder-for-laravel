<?php

declare(strict_types=1);

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use RoundlyConsulting\QueryBuilder\AllowedFilter;
use RoundlyConsulting\QueryBuilder\AllowedSort;
use RoundlyConsulting\QueryBuilder\Exceptions\AllowListAlreadyApplied;
use RoundlyConsulting\QueryBuilder\Exceptions\QueryBuilderException;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownFilter;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

function seedPosts(): void
{
    Post::create(['title' => 'Alpha', 'status' => 'published', 'views' => 30, 'active' => true]);
    Post::create(['title' => 'Beta', 'status' => 'draft', 'views' => 10, 'active' => false]);
    Post::create(['title' => 'Gamma', 'status' => 'published', 'views' => 20, 'active' => true]);
}

function qb(string $uri): QueryBuilder
{
    return QueryBuilder::for(Post::class, Request::create($uri));
}

it('applies allow-listed filters and sorts then paginates', function (): void {
    seedPosts();

    $result = qb('/?filter[status]=published&sort=-views')
        ->allowedFilters('status')
        ->allowedSorts('views')
        ->paginate(10);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($result->pluck('title')->all())->toBe(['Alpha', 'Gamma']);
});

it('forwards get to the underlying builder and returns a collection', function (): void {
    seedPosts();

    $result = qb('/')->allowedFilters('status')->get();

    expect($result)->toBeInstanceOf(Collection::class)->toHaveCount(3);
});

it('forwards first to the underlying builder', function (): void {
    seedPosts();

    $post = qb('/?sort=views')->allowedSorts('views')->first();

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->title)->toBe('Beta');
});

it('returns itself when a forwarded call returns a builder', function (): void {
    $builder = qb('/')->where('active', true);

    expect($builder)->toBeInstanceOf(QueryBuilder::class);
});

it('accepts a prepared builder and preserves its constraints', function (): void {
    seedPosts();

    $result = QueryBuilder::for(Post::query()->where('active', true), Request::create('/?sort=title'))
        ->allowedSorts('title')
        ->get();

    expect($result->pluck('title')->all())->toBe(['Alpha', 'Gamma']);
});

it('normalises a bare string filter to an exact match', function (): void {
    seedPosts();

    $result = qb('/?filter[status]=draft')->allowedFilters('status')->get();

    expect($result->pluck('title')->all())->toBe(['Beta']);
});

it('normalises a bare string sort to a field sort', function (): void {
    seedPosts();

    $result = qb('/?sort=-title')->allowedSorts('title')->get();

    expect($result->pluck('title')->all())->toBe(['Gamma', 'Beta', 'Alpha']);
});

it('accepts AllowedFilter and AllowedSort objects', function (): void {
    seedPosts();

    $result = qb('/?filter[state]=published&sort=-height')
        ->allowedFilters(AllowedFilter::exact('state', 'status'))
        ->allowedSorts(AllowedSort::field('height', 'views'))
        ->get();

    expect($result->pluck('title')->all())->toBe(['Alpha', 'Gamma']);
});

it('applies the default sort only when no sort param is present', function (): void {
    seedPosts();

    $withoutSort = qb('/')->allowedSorts('views')->defaultSort('-views')->get();
    expect($withoutSort->pluck('title')->all())->toBe(['Alpha', 'Gamma', 'Beta']);

    $withSort = qb('/?sort=views')->allowedSorts('views')->defaultSort('-views')->get();
    expect($withSort->pluck('title')->all())->toBe(['Beta', 'Gamma', 'Alpha']);
});

it('supports a multi-column default sort', function (): void {
    seedPosts();

    $result = qb('/')->allowedSorts('status', 'views')->defaultSort('status,-views')->get();

    expect($result->pluck('title')->all())->toBe(['Beta', 'Alpha', 'Gamma']);
});

it('falls back to a field sort for a default sort field outside the allow-list', function (): void {
    seedPosts();

    $result = qb('/')->allowedSorts('views')->defaultSort('title')->get();

    expect($result->pluck('title')->all())->toBe(['Alpha', 'Beta', 'Gamma']);
});

it('ignores empty tokens in a default sort string', function (): void {
    seedPosts();

    $result = qb('/')->allowedSorts('views')->defaultSort('-views,')->get();

    expect($result->pluck('title')->all())->toBe(['Alpha', 'Gamma', 'Beta']);
});

it('applies filters and sorts exactly once', function (): void {
    seedPosts();

    $builder = qb('/?filter[status]=published')->allowedFilters('status');

    $first = $builder->getEloquentBuilder();
    $second = $builder->getEloquentBuilder();

    expect($first)->toBe($second)
        ->and($first->getQuery()->wheres)->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| Declaration order
|--------------------------------------------------------------------------
|
| The request is applied the first time a call is forwarded to the builder, because a
| forwarded call may run the query. An allow-list declared after that point can no longer
| take effect, so it is refused loudly — before, it was silently ignored: a `filter[status]`
| the controller allowed answered 400, and a default sort vanished.
|
*/

it('refuses allowedFilters() declared after a forwarded builder call', function (): void {
    expect(fn () => qb('/')->where('views', '>', 0)->allowedFilters('status'))
        ->toThrow(AllowListAlreadyApplied::class, 'allowedFilters() was called after the request had already been applied to the query by where()');
});

it('refuses allowedSorts() declared after a forwarded builder call', function (): void {
    expect(fn () => qb('/')->with('author')->allowedSorts('title'))
        ->toThrow(AllowListAlreadyApplied::class, 'allowedSorts() was called after the request had already been applied to the query by with()');
});

it('refuses defaultSort() declared after a forwarded builder call', function (): void {
    expect(fn () => qb('/')->where('views', '>', 0)->defaultSort('-views'))
        ->toThrow(AllowListAlreadyApplied::class, 'defaultSort() was called after the request had already been applied to the query by where()');
});

it('checks a filtered request against the allow-list as it stood when it was applied', function (): void {
    // The misordered chain cannot be told apart from an endpoint that allows nothing at the
    // moment the request is applied, so a client's filter answers that allow-list's 400 —
    // the unfiltered request above is the one that surfaces the ordering mistake.
    expect(fn () => qb('/?filter[status]=draft')->where('views', '>', 0)->allowedFilters('status'))
        ->toThrow(UnknownFilter::class);
});

it('refuses a declaration after getEloquentBuilder()', function (): void {
    $builder = qb('/')->allowedFilters('status');
    $builder->getEloquentBuilder();

    expect(fn () => $builder->allowedFilters('views'))
        ->toThrow(AllowListAlreadyApplied::class, 'by getEloquentBuilder()');
});

it('is a logic exception carrying the package marker', function (): void {
    try {
        qb('/')->where('views', '>', 0)->allowedSorts('views');
    } catch (AllowListAlreadyApplied $exception) {
        expect($exception)->toBeInstanceOf(LogicException::class)
            ->toBeInstanceOf(QueryBuilderException::class);

        return;
    }

    test()->fail('Expected AllowListAlreadyApplied.');
});

it('applies an allow-list declared before a forwarded builder call', function (): void {
    seedPosts();

    $result = qb('/?filter[status]=published')
        ->allowedFilters('status')
        ->defaultSort('-views')
        ->where('views', '>', 15)
        ->get();

    expect($result->pluck('title')->all())->toBe(['Alpha', 'Gamma']);
});
