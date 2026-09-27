<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\Filters\NullableOperatorFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

beforeEach(function (): void {
    Post::create(['title' => 'A', 'author_id' => 1]);
    Post::create(['title' => 'B', 'author_id' => 2]);
    Post::create(['title' => 'C', 'author_id' => null]);
});

it('matches a value exactly', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id))->apply($query, '1', 'author_id');

    expect($query->pluck('title')->all())->toBe(['A']);
});

it('matches the unset rows on the sentinel', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id))->apply($query, 'none', 'author_id');

    expect($query->pluck('title')->all())->toBe(['C']);
});

it('matches the rows that have a value when the sentinel is negated', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id))->apply($query, 'not:none', 'author_id');

    expect($query->pluck('title')->all())->toBe(['A', 'B']);
});

it('includes the unset rows in a negation', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id))->apply($query, 'not:1', 'author_id');

    // "Not author 1" plainly covers the post with no author at all — the reading SQL's
    // own `!=` gets wrong.
    expect($query->pluck('title')->all())->toBe(['B', 'C']);
});

it('reads a negated multi value as "neither"', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id))->apply($query, ['not:1', '2'], 'author_id');

    expect($query->pluck('title')->all())->toBe(['C']);
});

it('combines the sentinel with a value', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id))->apply($query, ['1', 'none'], 'author_id');

    expect($query->pluck('title')->all())->toBe(['A', 'C']);
});

it('matches nothing when every value is the wrong shape for the column', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id))->apply($query, 'garbage', 'author_id');

    // Not a dropped filter: an unfiltered list reads as "the filter worked and everything
    // matched". And never a query — a bigint column answers request text with a 500.
    expect($query->pluck('title')->all())->toBe([]);
});

it('keeps the sentinel when the rest of the values are the wrong shape', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id))->apply($query, ['garbage', 'none'], 'author_id');

    expect($query->pluck('title')->all())->toBe(['C']);
});

it('does nothing when the parameter carries no value at all', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id))->apply($query, '', 'author_id');

    expect($query->pluck('title')->all())->toBe(['A', 'B', 'C']);
});

it('drops a non-scalar element instead of stringifying it', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id))->apply($query, ['1', ['nested']], 'author_id');

    // Casting the nested array would be an "Array to string conversion" warning, which
    // Laravel's handler promotes to an exception — one malformed query string taking the
    // whole endpoint down.
    expect($query->pluck('title')->all())->toBe(['A']);
});

it('treats an undeclared operator as part of the value', function (): void {
    $query = Post::query();
    // A text-shaped value belongs on a text column: against the bigint `author_id`, Postgres
    // rejects 'not:1' outright instead of matching nothing.
    (new NullableOperatorFilter(FilterValueShape::Text, []))->apply($query, 'not:1', 'summary');

    // `not` was not declared, so the whole string is the value — which no summary holds.
    expect($query->pluck('title')->all())->toBe([]);
});

it('honours an explicit is: on the sentinel', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id))->apply($query, 'is:none', 'author_id');

    expect($query->pluck('title')->all())->toBe(['C']);
});

it('takes a custom sentinel', function (): void {
    $query = Post::query();
    (new NullableOperatorFilter(FilterValueShape::Id, [RequestedOperator::Not], 'unset'))
        ->apply($query, 'unset', 'author_id');

    expect($query->pluck('title')->all())->toBe(['C']);
});
