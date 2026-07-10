<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Filters\ScopeFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

beforeEach(function (): void {
    Post::create(['title' => 'A', 'status' => 'published', 'views' => 5]);
    Post::create(['title' => 'B', 'status' => 'draft', 'views' => 50]);
    Post::create(['title' => 'C', 'status' => 'published', 'views' => 500]);
});

it('calls the model scope with a scalar value', function (): void {
    $query = Post::query();
    (new ScopeFilter)->apply($query, 'published', 'status');

    expect($query->pluck('title')->all())->toBe(['A', 'C']);
});

it('passes an array value as a single scope argument by default', function (): void {
    $query = Post::query();
    (new ScopeFilter)->apply($query, ['A', 'C'], 'titles');

    expect($query->pluck('title')->all())->toBe(['A', 'C']);
});

it('spreads an array value only when spreading is enabled', function (): void {
    $query = Post::query();
    (new ScopeFilter(spread: true))->apply($query, [10, 100], 'views_between');

    expect($query->pluck('title')->all())->toBe(['B']);
});
