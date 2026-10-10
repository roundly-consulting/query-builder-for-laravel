<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use RoundlyConsulting\QueryBuilder\AllowedFilter;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Author;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * `AllowedFilter::search()` against the engine the leg runs: sqlite on the matrix, Postgres on
 * `test-pgsql`, MySQL on `test-mysql`. The unit test pins the SQL each driver compiles; this
 * pins what that SQL returns.
 *
 * One row set and one table of `filter[search]` → titles, so a case reads as data: the rows a
 * URL must return, on every engine.
 */
const SEARCH_ROWS = [
    ['title' => 'Annual report', 'summary' => 'Q3 numbers', 'status' => 'published'],
    ['title' => 'Quarterly', 'summary' => 'Joanna writes', 'status' => 'published'],
    // NULL summary: still found by its title. A draft: excluded by `filter[status]`.
    ['title' => 'Annex', 'summary' => null, 'status' => 'draft'],
    ['title' => '50% off', 'summary' => null, 'status' => 'published'],
    ['title' => '5000 off', 'summary' => null, 'status' => 'published'],
    ['title' => 'a_b marker', 'summary' => null, 'status' => 'published'],
    ['title' => 'aXb marker', 'summary' => null, 'status' => 'published'],
    ['title' => 'back\\slash', 'summary' => null, 'status' => 'published'],
    ['title' => 'backslash', 'summary' => null, 'status' => 'published'],
    ['title' => 'Smith, John Ltd', 'summary' => null, 'status' => 'published'],
    // Matches `Smith` by title and `John` by summary: what an OR of the comma pieces finds.
    ['title' => 'Smith', 'summary' => 'John', 'status' => 'published'],
    ['title' => 'true story', 'summary' => null, 'status' => 'published'],
    ['title' => 'Ärger im Büro', 'summary' => null, 'status' => 'published'],
];

beforeEach(function (): void {
    foreach (SEARCH_ROWS as $row) {
        Post::create($row);
    }
});

/**
 * @return list<string>
 */
function searchTitles(string $query, AllowedFilter ...$filters): array
{
    return searchTitlesOf(Post::query(), $query, ...$filters);
}

/**
 * @param  Builder<Post>  $subject
 * @return list<string>
 */
function searchTitlesOf(Builder $subject, string $query, AllowedFilter ...$filters): array
{
    $filters = $filters === [] ? [AllowedFilter::search('search', ['title', 'summary'])] : $filters;

    /** @var list<string> $titles */
    $titles = QueryBuilder::for($subject, Request::create('/?'.$query))
        ->allowedFilters(...$filters)
        ->get()
        ->pluck('title')
        ->sort()
        ->values()
        ->all();

    return $titles;
}

/** @return list<string> */
function allSearchTitles(): array
{
    $titles = array_column(SEARCH_ROWS, 'title');
    sort($titles);

    return $titles;
}

it('matches the phrase in any of the columns', function (string $query, array $expected): void {
    expect(searchTitles($query))->toBe($expected);
})->with([
    'title or summary' => ['filter[search]=ann', ['Annex', 'Annual report', 'Quarterly']],
    'ASCII case folded' => ['filter[search]=ANN', ['Annex', 'Annual report', 'Quarterly']],
    'a NULL summary does not hide a title match' => ['filter[search]=annex', ['Annex']],
    'a literal percent' => ['filter[search]='.urlencode('50%'), ['50% off']],
    'a literal underscore' => ['filter[search]=a_b', ['a_b marker']],
    'a literal backslash' => ['filter[search]='.urlencode('back\\slash'), ['back\\slash']],
    'a comma is part of the phrase' => ['filter[search]='.urlencode('Smith, John'), ['Smith, John Ltd']],
    'array syntax is rejoined' => ['filter[search][]=Smith&filter[search][]='.urlencode(' John'), ['Smith, John Ltd']],
    'true is text' => ['filter[search]=true', ['true story']],
    'no operator prefix' => ['filter[search]='.urlencode('not:off'), []],
]);

it('returns the unfiltered list for an empty phrase', function (string $query): void {
    expect(searchTitles($query))->toBe(allSearchTitles());
})->with([
    'empty' => ['filter[search]='],
    'spaces' => ['filter[search]=%20%20'],
    'a lone comma' => ['filter[search]=,'],
]);

it('ANDs the grouped search with the other filters', function (): void {
    // `Annex` matches the search but is a draft. An OR that leaked out of its group
    // (`title like ? or summary like ? and status = ?`) would keep it.
    $titles = searchTitles(
        'filter[search]=ann&filter[status]=published',
        AllowedFilter::search('search', ['title', 'summary']),
        AllowedFilter::exact('status'),
    );

    expect($titles)->toBe(['Annual report', 'Quarterly']);
});

it('folds non-ASCII letters exactly as the configured engine documents', function (): void {
    [$caseFolded, $accentFolded] = match (DriverMatrix::driver()) {
        'sqlite' => [[], []],
        'pgsql' => [['Ärger im Büro'], []],
        default => [['Ärger im Büro'], ['Ärger im Büro']],
    };

    expect(searchTitles('filter[search]='.urlencode('ärger')))->toBe($caseFolded)
        ->and(searchTitles('filter[search]=arger'))->toBe($accentFolded)
        ->and(searchTitles('filter[search]='.urlencode('Ärger')))->toBe(['Ärger im Büro']);
});

it('searches a table-qualified column through a join', function (): void {
    $hannah = Author::create(['name' => 'Hannah']);
    $zed = Author::create(['name' => 'Zed']);
    Post::where('title', '50% off')->update(['author_id' => $hannah->id]);
    Post::where('title', 'Annual report')->update(['author_id' => $zed->id]);

    $joined = Post::query()
        ->join('authors', 'authors.id', '=', 'posts.author_id')
        ->select('posts.*');

    $titles = searchTitlesOf(
        $joined,
        'filter[search]=ann',
        AllowedFilter::search('search', ['posts.title', 'authors.name']),
    );

    // `50% off` by its author's name, `Annual report` by its own title.
    expect($titles)->toBe(['50% off', 'Annual report']);
});

it('compares an asText json column as case-folded text on every engine', function (): void {
    Post::create(['title' => 'Tagged', 'tags' => ['Foo']]);

    $search = AllowedFilter::search('search', ['title', 'tags'], asText: ['tags']);

    expect(searchTitles('filter[search]=foo', $search))->toBe(['Tagged'])
        ->and(searchTitles('filter[search]=FOO', $search))->toBe(['Tagged']);
});

it('cannot search an uncast json column on postgres', function (): void {
    Post::create(['title' => 'Tagged', 'tags' => ['Foo']]);

    // `json ilike unknown` does not exist (SQLSTATE 42883): the reason the docs say to
    // declare every non-text column in asText.
    expect(fn () => searchTitles('filter[search]=foo', AllowedFilter::search('search', ['title', 'tags'])))
        ->toThrow(fn (QueryException $e) => expect($e->getCode())->toBe('42883'));
})->skip(fn () => DriverMatrix::driver() !== 'pgsql', 'postgres only');

it('matches an uncast json column case-sensitively on mysql', function (): void {
    Post::create(['title' => 'Tagged', 'tags' => ['Foo']]);

    $search = AllowedFilter::search('search', ['title', 'tags']);

    // MySQL compares a JSON value as binary text: exact letters match, folded ones do not.
    // asText (`cast(tags as char)`) is what makes it follow the connection collation.
    expect(searchTitles('filter[search]=Foo', $search))->toBe(['Tagged'])
        ->and(searchTitles('filter[search]=foo', $search))->toBe([]);
})->skip(fn () => DriverMatrix::driver() !== 'mysql', 'mysql only');
