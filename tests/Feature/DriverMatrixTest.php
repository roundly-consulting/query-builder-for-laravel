<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\QueryBuilder\Filters\PartialFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * D — the driver matrix. This package ships the fleet's driver-*discriminating* filters
 * (`PartialFilter` compiles `ilike` on Postgres and `like` everywhere else, `NotPartialFilter`
 * the negations of both, all with an explicit `ESCAPE`), which is why it is one of only two
 * rows that runs all three engines.
 *
 * `tests/Unit/Filters/PartialFilterDriverTest.php` pins which operator each driver
 * *compiles to*, without opening a connection. That is a claim about the SQL string. This
 * file is the other half and the one no compile test can make: that the operator each
 * driver compiles **actually behaves as claimed against that engine**.
 *
 * The distinction is the whole reason the legs exist. translatable #39 was a `LIKE` without
 * `ESCAPE`: green on Postgres, zero rows on SQLite — a compile test would have passed on
 * both, because the SQL string was exactly what the code meant to emit. Only running it
 * finds it, and only running it on more than one engine finds it *before* a host does.
 */

/**
 * The driver-truth pin: the **env-declared** driver against what the **connection itself
 * answers**. It makes a lying leg impossible, and it fires automatically rather than
 * needing someone to read a skip count — a `test-pgsql` job that quietly ran SQLite (the
 * fleet's default failure mode, and exactly what a missing `TESTING_DB_DRIVER` causes)
 * fails here instead of reporting green.
 */
it('runs on the driver the leg declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()))
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});

/**
 * The operator this connection really compiles, per driver. Pinned as a match rather than a
 * single expectation so that adding a driver to the matrix forces a decision here instead of
 * silently inheriting the `like` default.
 */
it('compiles the operator its own driver calls for', function (): void {
    $query = Post::query();

    (new PartialFilter)->apply($query, 'world', 'title');

    $expected = match (DriverMatrix::driver()) {
        'pgsql' => 'ilike ? escape ?',
        default => 'like ? escape ?',
    };

    expect($query->toSql())->toContain($expected);

    if (DriverMatrix::driver() !== 'pgsql') {
        expect($query->toSql())->not->toContain('ilike');
    }
});

/**
 * Case-insensitivity is the claim `PartialFilter`'s docblock makes ("Postgres uses `ILIKE`
 * for case-insensitivity; the others a collation-insensitive `LIKE`"), and it is the claim
 * most likely to be false on an engine nobody ran. It is delivered by three *different*
 * mechanisms — Postgres' `ILIKE` operator, MySQL's case-insensitive default collation, and
 * SQLite's ASCII-only `LIKE` — so "it works" on one engine is no evidence at all for
 * another. This is the assertion the mysql leg exists to run.
 */
it('matches case-insensitively on the configured engine', function (): void {
    Post::create(['title' => 'Hello World']);
    Post::create(['title' => 'GOODBYE world']);
    Post::create(['title' => 'unrelated']);

    $query = Post::query();
    (new PartialFilter)->apply($query, 'WoRlD', 'title');

    expect($query->orderBy('id')->pluck('title')->all())
        ->toBe(['Hello World', 'GOODBYE world']);
});

/**
 * The `ESCAPE` clause against a real engine — the highest-risk line in this package.
 *
 * It is emitted as `escape ?` with the escape character **bound**, which is not obviously
 * portable: the drivers differ on whether the escape character may be a placeholder at all,
 * and MySQL additionally treats a backslash as an escape character inside string literals,
 * so the `'\\'` binding has a second meaning there that it has nowhere else. A wrong answer
 * here is not a crash — it is a filter that silently treats a user's `%` as a wildcard, so
 * `50%` quietly matches `5000`. That is a correctness bug a host would never see in a log.
 */
it('treats a user percent as literal data on the configured engine', function (): void {
    Post::create(['title' => '50% off sale']);
    Post::create(['title' => '5000 off sale']);

    $query = Post::query();
    (new PartialFilter)->apply($query, '50%', 'title');

    expect($query->pluck('title')->all())->toBe(['50% off sale']);
});

it('treats a user underscore as literal data on the configured engine', function (): void {
    Post::create(['title' => 'a_b marker']);
    Post::create(['title' => 'aXb marker']);

    $query = Post::query();
    (new PartialFilter)->apply($query, 'a_b', 'title');

    expect($query->pluck('title')->all())->toBe(['a_b marker']);
});

/**
 * The unmodelled-driver path, proven against a real engine rather than a compile.
 * `DatabaseDriver::current()` *throws* for a driver the toolkit's enum does not model, and a
 * filter runs inside a request — so `PartialFilter` deliberately uses `tryFrom()` and
 * degrades to a portable `like`. This pins that the degraded path still returns rows on the
 * engine actually under test, rather than only compiling.
 */
it('degrades to a portable like that still matches on the configured engine', function (): void {
    Post::create(['title' => 'Hello World']);

    $query = Post::query();
    (new PartialFilter)->apply($query, 'hello', 'title');

    expect($query->count())->toBe(1);
});
