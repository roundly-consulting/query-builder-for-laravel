<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\QueryBuilder\Filters\PartialFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

/**
 * The partial filter is the package's only driver-discriminating code: Postgres
 * gets a native case-insensitive `ILIKE`, every other driver a collation
 * `LIKE`. These connections are never opened — the query is compiled, not run —
 * so no server is needed to pin which operator each driver compiles to.
 */
function partialSqlOn(string $connection): string
{
    $post = new Post;
    $post->setConnection($connection);

    $query = $post->newQuery();

    (new PartialFilter)->apply($query, 'world', 'title');

    return $query->toSql();
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
