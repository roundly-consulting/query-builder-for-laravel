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

it('spreads an array value as scope arguments', function (): void {
    $query = Post::query();
    (new ScopeFilter)->apply($query, [10, 100], 'views_between');

    expect($query->pluck('title')->all())->toBe(['B']);
});
