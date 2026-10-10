<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\QueryBuilder\Exceptions\InvalidFilterDeclaration;
use RoundlyConsulting\QueryBuilder\Exceptions\QueryBuilderException;
use RoundlyConsulting\QueryBuilder\Filters\SearchFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

/**
 * Compile-only: these probe connections are never opened, so no server is needed to pin
 * what each driver compiles a search to. What the SQL then DOES on an engine is
 * `tests/Feature/SearchFilterTest.php`'s job, on every leg.
 *
 * @return Builder<Post>
 */
function searchOn(string $connection, SearchFilter $filter, mixed $value = 'world'): Builder
{
    $post = new Post;
    $post->setConnection($connection);

    $query = $post->newQuery();

    // The property is ignored: a search names its columns at declaration.
    $filter->apply($query, $value, 'ignored');

    return $query;
}

/**
 * The bindings a search on the default connection adds for `$value`.
 *
 * @return list<mixed>
 */
function searchBindings(mixed $value, SearchFilter $filter = new SearchFilter(['title'])): array
{
    $query = Post::query();
    $filter->apply($query, $value, 'search');

    return $query->getBindings();
}

beforeEach(function (): void {
    $server = ['host' => '127.0.0.1', 'database' => 'probe', 'username' => 'probe', 'password' => '', 'prefix' => ''];

    config()->set('database.connections.sqlite_probe', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('database.connections.pg_probe', ['driver' => 'pgsql', ...$server]);
    config()->set('database.connections.mysql_probe', ['driver' => 'mysql', ...$server]);
    config()->set('database.connections.mariadb_probe', ['driver' => 'mariadb', ...$server]);
    // A first-party Laravel driver the toolkit's DatabaseDriver enum does not model: the
    // search must degrade to `like`, never throw inside a request.
    config()->set('database.connections.sqlsrv_probe', ['driver' => 'sqlsrv', ...$server]);

    foreach (['sqlite_probe', 'pg_probe', 'mysql_probe', 'mariadb_probe', 'sqlsrv_probe'] as $probe) {
        DB::purge($probe);
    }
});

it('compiles one grouped OR of escaped likes per driver', function (string $connection, string $expected): void {
    expect(searchOn($connection, new SearchFilter(['title', 'summary']))->toSql())->toContain($expected);
})->with([
    'pgsql' => ['pg_probe', '("title" ilike ? escape ? or "summary" ilike ? escape ?)'],
    'mysql' => ['mysql_probe', '(`title` like ? escape ? or `summary` like ? escape ?)'],
    'mariadb' => ['mariadb_probe', '(`title` like ? escape ? or `summary` like ? escape ?)'],
    'sqlite' => ['sqlite_probe', '("title" like ? escape ? or "summary" like ? escape ?)'],
    'sqlsrv, unmodelled' => ['sqlsrv_probe', '([title] like ? escape ? or [summary] like ? escape ?)'],
]);

it('casts only the asText columns, the way each driver spells a cast to text', function (string $connection, string $expected): void {
    expect(searchOn($connection, new SearchFilter(['title', 'tags'], asText: ['tags']))->toSql())->toContain($expected);
})->with([
    'pgsql' => ['pg_probe', '("title" ilike ? escape ? or "tags"::text ilike ? escape ?)'],
    'mysql' => ['mysql_probe', '(`title` like ? escape ? or cast(`tags` as char) like ? escape ?)'],
    'mariadb' => ['mariadb_probe', '(`title` like ? escape ? or cast(`tags` as char) like ? escape ?)'],
    'sqlite' => ['sqlite_probe', '("title" like ? escape ? or "tags" like ? escape ?)'],
    'sqlsrv, unmodelled' => ['sqlsrv_probe', '([title] like ? escape ? or [tags] like ? escape ?)'],
]);

it('never lowercases a column, so a trigram index on it stays usable', function (): void {
    expect(searchOn('pg_probe', new SearchFilter(['title', 'tags'], asText: ['tags']))->toSql())
        ->not->toContain('lower(');
});

it('wraps a table-qualified column through the grammar', function (): void {
    expect(searchOn('pg_probe', new SearchFilter(['posts.title', 'posts.tags'], asText: ['posts.tags']))->toSql())
        ->toContain('("posts"."title" ilike ? escape ? or "posts"."tags"::text ilike ? escape ?)');

    expect(searchOn('mysql_probe', new SearchFilter(['posts.title']))->toSql())
        ->toContain('(`posts`.`title` like ? escape ?)');
});

it('binds one escaped needle per column, each with the escape character', function (): void {
    $bindings = searchOn('pg_probe', new SearchFilter(['title', 'summary']), '50%_a\\b\\')->getBindings();

    expect($bindings)->toBe(['%50\\%\\_a\\\\b\\\\%', '\\', '%50\\%\\_a\\\\b\\\\%', '\\']);
});

it('collapses duplicate columns, keeping the first-seen order', function (): void {
    $query = searchOn('pg_probe', new SearchFilter(['summary', 'title', 'summary', 'title'], asText: ['title', 'title']));

    expect($query->toSql())->toContain('("summary" ilike ? escape ? or "title"::text ilike ? escape ?)')
        ->and($query->getBindings())->toHaveCount(4);
});

it('refuses a declaration that names no column', function (): void {
    expect(fn () => new SearchFilter([]))->toThrow(InvalidFilterDeclaration::class, 'at least one column');
});

it('refuses a column that is not a bare or table-qualified name', function (string $column): void {
    expect(fn () => new SearchFilter(['title', $column]))
        ->toThrow(InvalidFilterDeclaration::class, "`{$column}`");
})->with([
    'a cast' => 'tags::text',
    'a function' => 'lower(name)',
    'an alias' => 'name as n',
    'a space' => 'a b',
    'three parts' => 'public.posts.title',
    'a leading digit' => '1title',
    'an empty name' => '',
    'a quoted name' => '"title"',
]);

it('points a cast column at asText', function (): void {
    expect(fn () => new SearchFilter(['tags::text']))->toThrow(InvalidFilterDeclaration::class, 'asText');
});

it('refuses an asText column it does not search', function (): void {
    expect(fn () => new SearchFilter(['title', 'summary'], asText: ['tags']))
        ->toThrow(InvalidFilterDeclaration::class, '`tags`');
});

it('throws a logic exception the package marker catches', function (): void {
    try {
        new SearchFilter([]);
    } catch (InvalidFilterDeclaration $e) {
        expect($e)->toBeInstanceOf(LogicException::class)
            ->and($e)->toBeInstanceOf(QueryBuilderException::class);

        return;
    }

    $this->fail('No InvalidFilterDeclaration was thrown.');
});

it('rejoins a comma-split list into the one phrase the client typed', function (): void {
    // `Smith, John` reaches a filter as ['Smith', ' John']; a search box holds one text.
    expect(searchBindings(['Smith', ' John']))->toBe(['%Smith, John%', '\\']);
});

it('caps the phrase after rejoining it, then trims it', function (): void {
    config()->set('query-builder.limits.max_value_length', 7);

    expect(searchBindings(['abcd', 'efgh']))->toBe(['%abcd,ef%', '\\'])
        // Capped first, so the cap counts the leading spaces the trim then removes.
        ->and(searchBindings('   abcdefgh'))->toBe(['%abcd%', '\\']);
});

it('trims spaces, tabs and non-breaking spaces off the phrase', function (): void {
    expect(searchBindings("\u{00A0}\t ann \u{00A0}\n"))->toBe(['%ann%', '\\'])
        // Only the ends: the inner space is part of the text.
        ->and(searchBindings(' Smith John '))->toBe(['%Smith John%', '\\']);
});

it('adds no constraint for an empty phrase', function (mixed $value): void {
    $query = Post::query();
    (new SearchFilter(['title', 'summary']))->apply($query, $value, 'search');

    expect($query->getQuery()->wheres)->toBe([]);
})->with([
    'empty' => '',
    'whitespace' => "   \t",
    'non-breaking space' => "\u{00A0}",
    'empty list' => [[]],
    'a lone comma, as the request splits it' => [['', '']],
    'a lone comma, unsplit' => [[',']],
    'only separators' => ' , , ',
    'null' => null,
    'an object' => new stdClass,
    'a nested list' => [[['a'], ['b']]],
]);

it('spells out a non-string scalar', function (mixed $value, string $needle): void {
    expect(searchBindings($value))->toBe([$needle, '\\']);
})->with([
    'true' => [true, '%true%'],
    'false' => [false, '%false%'],
    'an int' => [42, '%42%'],
    'a float' => [4.5, '%4.5%'],
    'a list of scalars' => [[true, 7, 'x'], '%true,7,x%'],
]);

it('reads no operator prefix: the whole text is searched', function (): void {
    expect(searchBindings('not:draft'))->toBe(['%not:draft%', '\\']);
});
