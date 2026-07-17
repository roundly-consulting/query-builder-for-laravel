<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Filters\ExactFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

beforeEach(function (): void {
    Post::create(['title' => 'A', 'status' => 'published', 'views' => 1, 'active' => true]);
    Post::create(['title' => 'B', 'status' => 'draft', 'views' => 2, 'active' => false]);
    Post::create(['title' => 'C', 'status' => 'archived', 'views' => 3, 'active' => true]);
});

it('matches a scalar value with an equality clause', function (): void {
    $query = Post::query();
    (new ExactFilter)->apply($query, 'published', 'status');

    expect($query->toSql())->toContain(wrapped('status').' = ?')
        ->and($query->getBindings())->toBe(['published'])
        ->and($query->pluck('title')->all())->toBe(['A']);
});

it('matches an array value with a whereIn clause', function (): void {
    $query = Post::query();
    (new ExactFilter)->apply($query, ['published', 'archived'], 'status');

    expect($query->toSql())->toContain(wrapped('status').' in (?, ?)')
        ->and($query->pluck('title')->all())->toBe(['A', 'C']);
});

it('applies against a custom internal column', function (): void {
    $query = Post::query();
    (new ExactFilter)->apply($query, true, 'active');

    expect($query->pluck('title')->all())->toBe(['A', 'C']);
});
