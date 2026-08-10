<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\QueryBuilder\Filters\JsonContainsOperatorFilter;
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
