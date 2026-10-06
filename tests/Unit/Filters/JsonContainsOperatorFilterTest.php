<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\QueryBuilder\AllowedFilter;
use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Filters\JsonContainsOperatorFilter;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

beforeEach(function (): void {
    Post::create(['title' => 'A', 'tags' => ['release', 'docs']]);
    Post::create(['title' => 'B', 'tags' => ['docs']]);
    Post::create(['title' => 'C', 'tags' => []]);
    // A row with no tags column value at all — the one a negation must still match.
    DB::table('posts')->insert(['title' => 'D', 'status' => 'draft', 'tags' => null]);
});

it('matches rows carrying the value', function (): void {
    $query = Post::query();
    (new JsonContainsOperatorFilter)->apply($query, 'release', 'tags');

    expect($query->pluck('title')->all())->toBe(['A']);
});

it('ORs several values', function (): void {
    $query = Post::query();
    (new JsonContainsOperatorFilter)->apply($query, ['release', 'docs'], 'tags');

    expect($query->pluck('title')->all())->toBe(['A', 'B']);
});

it('excludes every row carrying the value, including the untagged ones', function (): void {
    $query = Post::query();
    (new JsonContainsOperatorFilter)->apply($query, 'not:release', 'tags');

    expect($query->pluck('title')->all())->toBe(['B', 'C', 'D']);
});

it('reads a negated multi value as "none of these"', function (): void {
    $query = Post::query();
    (new JsonContainsOperatorFilter)->apply($query, ['not:release', 'docs'], 'tags');

    expect($query->pluck('title')->all())->toBe(['C', 'D']);
});

it('does not match a value nested inside another tag', function (): void {
    Post::create(['title' => 'E', 'tags' => ['pre-release']]);

    $query = Post::query();
    (new JsonContainsOperatorFilter)->apply($query, 'release', 'tags');

    expect($query->pluck('title')->all())->toBe(['A']);
});

it('does nothing when the parameter carries no value at all', function (): void {
    $query = Post::query();
    (new JsonContainsOperatorFilter)->apply($query, '', 'tags');

    expect($query->pluck('title')->all())->toBe(['A', 'B', 'C', 'D']);
});

it('matches an id array through an Id shape, and answers a misshapen value without a query', function (): void {
    // JSON membership is type-strict on every engine: the text "5" is not the number 5. An
    // id array needs its values bound as integers, which only the declared shape can say.
    Post::create(['title' => 'E', 'tags' => [5, 7]]);
    Post::create(['title' => 'F', 'tags' => ['5', 'x']]);

    $titles = fn (string $raw, FilterValueShape $shape = FilterValueShape::Id): array => QueryBuilder::for(
        Post::class,
        Request::create('/?filter[tags]='.urlencode($raw)),
    )
        ->allowedFilters(AllowedFilter::jsonContains('tags', shape: $shape))
        ->get()
        ->pluck('title')
        ->sort()
        ->values()
        ->all();

    expect($titles('5'))->toBe(['E'])
        ->and($titles('9,7'))->toBe(['E'])
        ->and($titles('garbage,5'))->toBe(['E'])
        ->and($titles('not:5'))->toBe(['A', 'B', 'C', 'D', 'F'])
        // Nothing fits the column: a positive match answers empty, a negation excludes nobody.
        ->and($titles('garbage'))->toBe([])
        ->and($titles('not:garbage'))->toBe(['A', 'B', 'C', 'D', 'E', 'F'])
        // The default Text shape is unchanged: it binds the text.
        ->and($titles('5', FilterValueShape::Text))->toBe(['F']);
});
