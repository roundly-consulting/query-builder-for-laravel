<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Filters\PartialFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

beforeEach(function (): void {
    Post::create(['title' => 'Hello World']);
    Post::create(['title' => 'GOODBYE world']);
    Post::create(['title' => 'unrelated']);
    Post::create(['title' => '50% off sale']);
});

it('matches case-insensitively on a substring', function (): void {
    $query = Post::query();
    (new PartialFilter)->apply($query, 'world', 'title');

    expect($query->pluck('title')->all())->toBe(['Hello World', 'GOODBYE world']);
});

it('escapes like wildcards in the bound needle', function (): void {
    $query = Post::query();
    (new PartialFilter)->apply($query, '50%', 'title');

    expect($query->getBindings())->toBe(['%50\\%%']);
});

it('escapes the underscore wildcard', function (): void {
    $query = Post::query();
    (new PartialFilter)->apply($query, 'a_b', 'title');

    expect($query->getBindings())->toBe(['%a\\_b%']);
});

it('groups an array value into an OR of partial matches', function (): void {
    $query = Post::query();
    (new PartialFilter)->apply($query, ['hello', 'goodbye'], 'title');

    expect($query->getBindings())->toBe(['%hello%', '%goodbye%'])
        ->and($query->pluck('title')->all())->toBe(['Hello World', 'GOODBYE world']);
});
