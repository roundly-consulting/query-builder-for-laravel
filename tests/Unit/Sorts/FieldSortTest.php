<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Enums\SortDirection;
use RoundlyConsulting\QueryBuilder\Sorts\FieldSort;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

it('orders ascending', function (): void {
    $query = Post::query();
    (new FieldSort)->apply($query, SortDirection::Ascending, 'title');

    expect($query->toSql())->toContain('order by '.wrapped('title').' asc');
});

it('orders descending', function (): void {
    $query = Post::query();
    (new FieldSort)->apply($query, SortDirection::Descending, 'title');

    expect($query->toSql())->toContain('order by '.wrapped('title').' desc');
});

it('sorts rows in the requested order', function (): void {
    Post::create(['title' => 'B']);
    Post::create(['title' => 'A']);
    Post::create(['title' => 'C']);

    $query = Post::query();
    (new FieldSort)->apply($query, SortDirection::Ascending, 'title');

    expect($query->pluck('title')->all())->toBe(['A', 'B', 'C']);
});
