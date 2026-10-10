<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Filters\NotPartialFilter;
use RoundlyConsulting\QueryBuilder\Filters\PartialFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

/**
 * The partial filters are the package's driver-discriminating code: Postgres
 * gets a native case-insensitive `ILIKE`, every other driver a collation
 * `LIKE`. These connections are never opened — the query is compiled, not run —
 * so no server is needed to pin which operator each driver compiles to.
 *
 * `NotPartialFilter` is pinned alongside `PartialFilter` rather than trusted to
 * mirror it: both now compile through the shared `LikeClause`, and a shared
 * helper is exactly where a negation flag or a driver branch breaks for one
 * caller and not the other — "they use the same helper" is not a test either.
 */
function sqlOn(string $connection, Filter $filter): string
{
    $post = new Post;
    $post->setConnection($connection);

    $query = $post->newQuery();

    $filter->apply($query, 'world', 'title');

    return $query->toSql();
}

function partialSqlOn(string $connection): string
{
    return sqlOn($connection, new PartialFilter);
}

function notPartialSqlOn(string $connection): string
{
    return sqlOn($connection, new NotPartialFilter);
}

beforeEach(function (): void {
    // A sqlite probe, for the same reason as the two below: this file pins which operator
    // each driver *compiles to*, so each driver needs a connection of its own that says what
    // it is. The sqlite case used to compile against the default `testing` connection and
    // assert it produced `like` — which was only true because the whole suite happened to
    // run on sqlite. On the pgsql leg `testing` IS Postgres, so that test compiled `ilike`
    // and went red: it had never been a test of sqlite, only of "whatever the suite is
    // running on", and its name said otherwise.
    config()->set('database.connections.sqlite_probe', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    config()->set('database.connections.pg_probe', [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'database' => 'probe',
        'username' => 'probe',
        'password' => '',
        'prefix' => '',
    ]);

    // A first-party Laravel driver the toolkit's DatabaseDriver enum does NOT
    // model — the case that decides whether adopting the enum may throw.
    config()->set('database.connections.sqlsrv_probe', [
        'driver' => 'sqlsrv',
        'host' => '127.0.0.1',
        'database' => 'probe',
        'username' => 'probe',
        'password' => '',
        'prefix' => '',
    ]);

    DB::purge('sqlite_probe');
    DB::purge('pg_probe');
    DB::purge('sqlsrv_probe');
});

it('compiles a case-insensitive ilike on postgres', function (): void {
    expect(partialSqlOn('pg_probe'))->toContain('ilike ? escape ?');
});

it('compiles a like on sqlite', function (): void {
    expect(partialSqlOn('sqlite_probe'))
        ->toContain('like ? escape ?')
        ->not->toContain('ilike');
});

it('degrades to like on a driver the toolkit enum does not model', function (): void {
    // `DatabaseDriver::current()` throws for an unmodelled driver. A filter runs
    // inside a request, so this must stay a portable `like` — never a 500 on a
    // list endpoint a host is already serving.
    expect(partialSqlOn('sqlsrv_probe'))
        ->toContain('like ? escape ?')
        ->not->toContain('ilike');
});

it('compiles the negation the same way on every driver', function (): void {
    expect(notPartialSqlOn('pg_probe'))->toContain('not ilike ? escape ?');

    expect(notPartialSqlOn('sqlite_probe'))
        ->toContain('not like ? escape ?')
        ->not->toContain('ilike');

    expect(notPartialSqlOn('sqlsrv_probe'))
        ->toContain('not like ? escape ?')
        ->not->toContain('ilike');
});
