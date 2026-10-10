<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\QueryBuilder\Filters\SearchFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * The index contract, on Postgres: a `gin_trgm_ops` index is built on the COLUMN, so a search
 * that keeps the column bare (`title ilike ?`) can use it and one that wraps it in an
 * expression (`lower(title) like ?`) cannot. That is why `SearchFilter` never lowercases and
 * casts only the columns declared in `asText`.
 *
 * `enable_seqscan = off` makes the planner take any index that CAN answer the predicate, so an
 * empty table still tells us which predicates an index matches.
 */
beforeEach(function (): void {
    if (DriverMatrix::driver() !== 'pgsql') {
        $this->markTestSkipped('postgres only');
    }

    try {
        DB::statement('create extension if not exists pg_trgm');
    } catch (Throwable $e) {
        $this->markTestSkipped('pg_trgm is not available: '.$e->getMessage());
    }

    DB::statement('create index posts_title_trgm on posts using gin (title gin_trgm_ops)');
    DB::statement('create index posts_summary_trgm on posts using gin (summary gin_trgm_ops)');
    DB::statement('set enable_seqscan = off');
});

/**
 * The plan Postgres picks for a query, as one string.
 *
 * @param  Builder<Post>  $query
 */
function planFor(Builder $query): string
{
    $rows = DB::select('explain '.$query->toSql(), $query->getBindings());

    return implode("\n", array_map(static fn (object $row): string => (string) array_values((array) $row)[0], $rows));
}

/**
 * @param  list<string>  $columns
 * @param  list<string>  $asText
 * @return Builder<Post>
 */
function trigramSearch(array $columns, array $asText = []): Builder
{
    $query = Post::query();
    (new SearchFilter($columns, $asText))->apply($query, 'annual', 'search');

    return $query;
}

it('uses the trigram index on a bare column', function (): void {
    expect(planFor(trigramSearch(['title'])))->toContain('posts_title_trgm');
});

it('uses one trigram index per column across a multi-column search', function (): void {
    expect(planFor(trigramSearch(['title', 'summary'])))
        ->toContain('BitmapOr')
        ->toContain('posts_title_trgm')
        ->toContain('posts_summary_trgm');
});

it('still uses the trigram index when a varchar column is cast to text', function (): void {
    // `varchar` -> `text` is a binary-coercible relabel, and Postgres applies it to a bare
    // varchar operand of `ilike` anyway (the plan prints `(title)::text ~~* …` for both): the
    // explicit cast builds the very same predicate, so declaring a varchar column in asText
    // costs nothing on Postgres. Measured on PostgreSQL 16.
    $cast = planFor(trigramSearch(['title'], asText: ['title']));

    expect($cast)->toContain('posts_title_trgm')
        ->and($cast)->toBe(planFor(trigramSearch(['title'])));
});

it('cannot use the trigram index once the column is lowercased', function (): void {
    // The contrast pin: the predicate the hand-rolled callbacks used, `lower(col) like ?`, is
    // an expression the column index does not match, so Postgres scans the table.
    $query = Post::query()->whereRaw('lower(title) like ?', ['%annual%']);

    expect(planFor($query))
        ->not->toContain('posts_title_trgm')
        ->toContain('Seq Scan');
});
