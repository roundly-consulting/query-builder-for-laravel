<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Filters\TrashedFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\ArchivedPost;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Author;
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

it('uses the model custom soft-delete column for only mode', function (): void {
    ArchivedPost::create(['title' => 'Kept']);
    ArchivedPost::create(['title' => 'Archived'])->delete();

    $query = ArchivedPost::query();
    (new TrashedFilter)->apply($query, 'only', 'trashed');

    expect($query->toSql())->toContain(wrapped('archived_at').' is not null')
        ->and($query->toSql())->not->toContain('deleted_at')
        ->and($query->pluck('title')->all())->toBe(['Archived']);
});

it('falls back to the default deleted_at column without the soft-delete trait', function (): void {
    $query = Author::query();
    (new TrashedFilter)->apply($query, 'only', 'trashed');

    expect($query->toSql())->toContain(wrapped('authors.deleted_at').' is not null');
});
