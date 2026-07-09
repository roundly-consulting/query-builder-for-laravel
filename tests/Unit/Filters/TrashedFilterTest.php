<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Filters\TrashedFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

beforeEach(function (): void {
    Post::create(['title' => 'Live']);
    $trashed = Post::create(['title' => 'Trashed']);
    $trashed->delete();
});

it('includes trashed rows for the with value', function (): void {
    $query = Post::query();
    (new TrashedFilter)->apply($query, 'with', 'trashed');

    expect($query->pluck('title')->all())->toBe(['Live', 'Trashed']);
});

it('returns only trashed rows for the only value', function (): void {
    $query = Post::query();
    (new TrashedFilter)->apply($query, 'only', 'trashed');

    expect($query->pluck('title')->all())->toBe(['Trashed']);
});

it('leaves the default non-trashed scope in place otherwise', function (): void {
    $query = Post::query();
    (new TrashedFilter)->apply($query, null, 'trashed');

    expect($query->pluck('title')->all())->toBe(['Live']);
});
