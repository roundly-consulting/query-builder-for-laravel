<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Filters\RelationOperatorFilter;
use RoundlyConsulting\QueryBuilder\Support\FilterSentinel;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Author;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

beforeEach(function (): void {
    $ada = Author::create(['name' => 'Ada']);
    $bob = Author::create(['name' => 'Bob']);
    Author::create(['name' => 'Cleo']);

    // Ada has BOTH a published and a draft post — the row a negated `whereHas` keeps by
    // mistake, because her other post still satisfies the subquery.
    Post::create(['title' => 'A1', 'status' => 'published', 'author_id' => $ada->id]);
    Post::create(['title' => 'A2', 'status' => 'draft', 'author_id' => $ada->id]);
    Post::create(['title' => 'B1', 'status' => 'draft', 'author_id' => $bob->id]);
});

it('matches rows that have a related row', function (): void {
    $query = Author::query();
    (new RelationOperatorFilter('posts.status'))->apply($query, 'published', 'posts');

    expect($query->pluck('name')->all())->toBe(['Ada']);
});

it('excludes every row related to the value when negated', function (): void {
    $query = Author::query();
    (new RelationOperatorFilter('posts.status'))->apply($query, 'not:published', 'posts');

    // Ada is excluded even though her draft post matches the subquery — `whereDoesntHave`,
    // never a negated `whereHas`. Cleo has no posts at all, so she cannot have that one.
    expect($query->pluck('name')->all())->toBe(['Bob', 'Cleo']);
});

it('reads a multi value as any-of, and its negation as none-of', function (): void {
    $any = Author::query();
    (new RelationOperatorFilter('posts.status'))->apply($any, ['published', 'draft'], 'posts');

    $none = Author::query();
    (new RelationOperatorFilter('posts.status'))->apply($none, ['not:published', 'draft'], 'posts');

    expect($any->pluck('name')->all())->toBe(['Ada', 'Bob'])
        ->and($none->pluck('name')->all())->toBe(['Cleo']);
});

it('matches nothing when the value is the wrong shape for the key', function (): void {
    $query = Author::query();
    (new RelationOperatorFilter('posts.id', FilterValueShape::Id))->apply($query, 'garbage', 'posts');

    expect($query->pluck('name')->all())->toBe([]);
});

it('excludes nothing when a negated value is the wrong shape for the key', function (): void {
    $query = Author::query();
    (new RelationOperatorFilter('posts.id', FilterValueShape::Id))->apply($query, 'not:garbage', 'posts');

    // No row can be related to an id the column cannot hold, so excluding it excludes
    // nobody.
    expect($query->pluck('name')->all())->toBe(['Ada', 'Bob', 'Cleo']);
});

it('does nothing when the parameter carries no value at all', function (): void {
    $query = Author::query();
    (new RelationOperatorFilter('posts.status'))->apply($query, '', 'posts');

    expect($query->pluck('name')->all())->toBe(['Ada', 'Bob', 'Cleo']);
});

it('matches rows related to nothing on the sentinel', function (): void {
    $query = Author::query();
    (new RelationOperatorFilter('posts.status', sentinel: FilterSentinel::NONE))
        ->apply($query, 'none', 'posts');

    expect($query->pluck('name')->all())->toBe(['Cleo']);
});

it('matches rows related to anything when the sentinel is negated', function (): void {
    $query = Author::query();
    (new RelationOperatorFilter('posts.status', sentinel: FilterSentinel::NONE))
        ->apply($query, 'not:none', 'posts');

    expect($query->pluck('name')->all())->toBe(['Ada', 'Bob']);
});

it('ORs the sentinel with a named value', function (): void {
    $query = Author::query();
    (new RelationOperatorFilter('posts.status', sentinel: FilterSentinel::NONE))
        ->apply($query, ['none', 'published'], 'posts');

    // "No posts at all, or a published one."
    expect($query->pluck('name')->all())->toBe(['Ada', 'Cleo']);
});

it('reads a negated sentinel plus a value as "has one, but not that one"', function (): void {
    $query = Author::query();
    (new RelationOperatorFilter('posts.status', sentinel: FilterSentinel::NONE))
        ->apply($query, ['not:none', 'published'], 'posts');

    expect($query->pluck('name')->all())->toBe(['Bob']);
});

it('treats the sentinel as an ordinary value when none is configured', function (): void {
    $query = Author::query();
    (new RelationOperatorFilter('posts.status'))->apply($query, 'none', 'posts');

    // No post has the status `none`, so this is an ordinary — empty — match.
    expect($query->pluck('name')->all())->toBe([]);
});
