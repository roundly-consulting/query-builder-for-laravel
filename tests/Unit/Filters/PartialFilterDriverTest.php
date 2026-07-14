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

    DB::purge('pg_probe');
    DB::purge('sqlsrv_probe');
});

it('compiles a case-insensitive ilike on postgres', function (): void {
    expect(partialSqlOn('pg_probe'))->toContain('ilike ? escape ?');
});

it('compiles a like on sqlite', function (): void {
    expect(partialSqlOn('testing'))
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
