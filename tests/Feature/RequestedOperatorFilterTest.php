<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\QueryBuilder\AllowedFilter;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

/**
 * `AllowedFilter::operators()` is the one place a REQUEST influences which comparison a
 * filter performs, so the tests weight the security properties over the happy path:
 * every path from request text to a query shape has to be one a developer enumerated.
 */
function operatorBuilder(string $uri): QueryBuilder
{
    return QueryBuilder::for(Post::class, Request::create($uri));
}

/**
 * The matched titles, sorted.
 *
 * Sorted because these cases assert WHICH rows matched, never in what order — and several
 * of them UPDATE a row first, which on Postgres rewrites it to the end of the heap. An
 * unordered `pluck` therefore compares a different sequence per engine, and the suite runs
 * on three.
 *
 * @return list<string>
 */
function matchedTitles(QueryBuilder $builder): array
{
    /** @var list<string> */
    return $builder->get()->pluck('title')->sort()->values()->all();
}

beforeEach(function (): void {
    Post::create(['title' => 'Alpha release', 'status' => 'published', 'views' => 30]);
    Post::create(['title' => 'Beta release', 'status' => 'draft', 'views' => 10]);
    Post::create(['title' => 'Gamma', 'status' => 'archived', 'views' => 50]);
});

/*
|--------------------------------------------------------------------------
| Backwards compatibility
|--------------------------------------------------------------------------
*/

it('treats a bare value as equality, exactly as before', function (): void {
    $result = matchedTitles(
        operatorBuilder('/?filter[status]=draft')
            ->allowedFilters(AllowedFilter::operators('status', [RequestedOperator::Not]))
    );

    expect($result)->toBe(['Beta release']);
});

it('accepts an explicit is: prefix as the same thing', function (): void {
    // A client must be able to switch BACK to equality after picking another operator, so
    // the filter's own DEFAULT is always nameable on top of what it declared.
    $result = matchedTitles(
        operatorBuilder('/?filter[status]=is:draft')
            ->allowedFilters(AllowedFilter::operators('status', [RequestedOperator::Not]))
    );

    expect($result)->toBe(['Beta release']);
});

it('leaves a normalized BOOLEAN value untouched', function (): void {
    // `QueryBuilderRequest` turns `true`/`false` into real booleans. Casting one to a string
    // to look for a `:` destroys it — `false` becomes `''`, which Postgres rejects outright
    // on a boolean column (`invalid input syntax for type boolean`) and mysql/sqlite
    // silently coerce to 0. Only a string can carry an operator prefix, so a non-string
    // value must reach the query exactly as received.
    Post::query()->where('title', 'Alpha release')->update(['active' => true]);

    $filters = AllowedFilter::operators('active', [RequestedOperator::Not]);

    expect(matchedTitles(operatorBuilder('/?filter[active]=true')->allowedFilters($filters)))
        ->toBe(['Alpha release']);
    expect(matchedTitles(operatorBuilder('/?filter[active]=false')->allowedFilters($filters)))
        ->toBe(['Beta release', 'Gamma']);
});

/*
|--------------------------------------------------------------------------
| The declared operators
|--------------------------------------------------------------------------
*/

it('applies not:', function (): void {
    $result = matchedTitles(
        operatorBuilder('/?filter[status]=not:draft')
            ->allowedFilters(AllowedFilter::operators('status', [RequestedOperator::Not]))
    );

    expect($result)->toBe(['Alpha release', 'Gamma']);
});

it('applies contains: and starts:', function (): void {
    expect(matchedTitles(
        operatorBuilder('/?filter[title]=contains:release')
            ->allowedFilters(AllowedFilter::operators('title', [RequestedOperator::Contains]))
    ))->toBe(['Alpha release', 'Beta release']);

    expect(matchedTitles(
        operatorBuilder('/?filter[title]=starts:Alpha')
            ->allowedFilters(AllowedFilter::operators('title', [RequestedOperator::StartsWith]))
    ))->toBe(['Alpha release']);
});

it('applies ncontains: and includes rows with NO value', function (): void {
    // A NULL column does not match a SQL `not like`, but "not containing X" plainly
    // includes a row that contains nothing at all. `summary` is nullable for this.
    Post::query()->where('title', 'Alpha release')->update(['summary' => 'the draft notes']);
    Post::query()->where('title', 'Beta release')->update(['summary' => 'shipped']);
    // Gamma keeps a NULL summary.

    $result = matchedTitles(
        operatorBuilder('/?filter[summary]=ncontains:draft')
            ->allowedFilters(AllowedFilter::operators('summary', [RequestedOperator::NotContains]))
    );

    expect($result)->toBe(['Beta release', 'Gamma']);
});

it('includes rows with NO value on not: too, for the same reason', function (): void {
    Post::query()->where('title', 'Alpha release')->update(['summary' => 'draft']);

    $result = matchedTitles(
        operatorBuilder('/?filter[summary]=not:draft')
            ->allowedFilters(AllowedFilter::operators('summary', [RequestedOperator::Not]))
    );

    expect($result)->toBe(['Beta release', 'Gamma']);
});

it('applies the comparison operators', function (): void {
    $filters = AllowedFilter::operators('views', [
        RequestedOperator::GreaterThan,
        RequestedOperator::LessThanOrEqual,
    ]);

    expect(matchedTitles(operatorBuilder('/?filter[views]=gt:20')->allowedFilters($filters)))
        ->toBe(['Alpha release', 'Gamma']);
    expect(matchedTitles(operatorBuilder('/?filter[views]=lte:10')->allowedFilters($filters)))
        ->toBe(['Beta release']);
});

it('applies one operator across a comma-joined multi value', function (): void {
    // `not:a,b` reads as "neither" — the operator is per FILTER, not per value.
    $result = matchedTitles(
        operatorBuilder('/?filter[status]=not:draft,archived')
            ->allowedFilters(AllowedFilter::operators('status', [RequestedOperator::Not]))
    );

    expect($result)->toBe(['Alpha release']);
});

it('applies the operator per FILTER, not per value', function (): void {
    // A token on a later array element is not an operator — the first element decides and
    // the rest stay values, so one wire value can never carry two comparisons.
    $result = matchedTitles(
        operatorBuilder('/?filter[status][]=draft&filter[status][]='.urlencode('not:archived'))
            ->allowedFilters(AllowedFilter::operators('status', [RequestedOperator::Not]))
    );

    expect($result)->toBe(['Beta release']);
});

it('ignores an empty needle on either negation instead of emptying the list', function (): void {
    // `not like '%%'` is FALSE for every non-null row, so a naive negation of the empty
    // needle hides the whole table. `PartialFilter` treats an empty value as a match-all
    // no-op; its negation has to agree, or one stray trailing comma from a UI joining a
    // multi-select turns "exclude drafts" into "only rows with no value".
    Post::query()->where('title', 'Alpha release')->update(['summary' => 'draft notes']);
    Post::query()->where('title', 'Beta release')->update(['summary' => 'shipped']);
    // Gamma keeps a NULL summary.

    $all = ['Alpha release', 'Beta release', 'Gamma'];

    expect(matchedTitles(
        operatorBuilder('/?filter[summary]='.urlencode('ncontains:'))
            ->allowedFilters(AllowedFilter::operators('summary', [RequestedOperator::NotContains]))
    ))->toBe($all);

    expect(matchedTitles(
        operatorBuilder('/?filter[summary]='.urlencode('not:'))
            ->allowedFilters(AllowedFilter::operators('summary', [RequestedOperator::Not]))
    ))->toBe($all);

    // The one that actually bit: a trailing comma must not swallow the rows that DO have a
    // summary but do not contain the needle.
    expect(matchedTitles(
        operatorBuilder('/?filter[summary]='.urlencode('ncontains:draft,'))
            ->allowedFilters(AllowedFilter::operators('summary', [RequestedOperator::NotContains]))
    ))->toBe(['Beta release', 'Gamma']);
});

/*
|--------------------------------------------------------------------------
| The allow-list is the security boundary
|--------------------------------------------------------------------------
*/

it('treats an operator the filter did NOT declare as part of the value', function (): void {
    // `contains` is a real token, but this filter only offers `not`. It must not silently
    // upgrade to a LIKE — and it must not error either; it filters for the literal text.
    $result = matchedTitles(
        operatorBuilder('/?filter[status]=contains:draft')
            ->allowedFilters(AllowedFilter::operators('status', [RequestedOperator::Not]))
    );

    expect($result)->toBeEmpty();
});

it('treats an unknown token as part of the value', function (): void {
    $result = matchedTitles(
        operatorBuilder('/?filter[status]=nonsense:draft')
            ->allowedFilters(AllowedFilter::operators('status', [RequestedOperator::Not]))
    );

    expect($result)->toBeEmpty();
});

it('never lets a SQL fragment reach an operator position', function (): void {
    // The blast radius of a hostile prefix is "your filter matched nothing".
    foreach (['1=1 or 1', "'; drop table posts; --", '= OR true', 'like'] as $hostile) {
        $result = matchedTitles(
            operatorBuilder('/?filter[status]='.urlencode($hostile.':draft'))
                ->allowedFilters(AllowedFilter::operators('status', RequestedOperator::cases()))
        );

        expect($result)->toBeEmpty();
    }

    // And the table is still there.
    expect(Post::count())->toBe(3);
});

it('keeps a colon that is part of a real value', function (): void {
    Post::create(['title' => 'https://example.test', 'status' => 'published', 'views' => 1]);

    $result = matchedTitles(
        operatorBuilder('/?filter[title]='.urlencode('https://example.test'))
            ->allowedFilters(AllowedFilter::operators('title', [RequestedOperator::Contains]))
    );

    expect($result)->toBe(['https://example.test']);
});

it('behaves exactly like an exact filter when no operators are declared', function (): void {
    $result = matchedTitles(
        operatorBuilder('/?filter[status]=not:draft')
            ->allowedFilters(AllowedFilter::operators('status', []))
    );

    // `not:` is not offered, so it is literal text — and matches nothing.
    expect($result)->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| The configured default
|--------------------------------------------------------------------------
*/

it('can default to a partial match for a text search field', function (): void {
    $result = matchedTitles(
        operatorBuilder('/?filter[title]=release')
            ->allowedFilters(AllowedFilter::operators(
                'title',
                [RequestedOperator::Not, RequestedOperator::Contains],
                partialByDefault: true,
            ))
    );

    expect($result)->toBe(['Alpha release', 'Beta release']);
});

it('makes the CONFIGURED default nameable, and nothing else', function (): void {
    // On a search field the default is `contains`, so `contains:` must work explicitly even
    // though it was not listed — and `is:` must NOT, or pasting `is:done` into the search box
    // silently exact-matches `done` instead of searching for the text somebody typed.
    Post::create(['title' => 'is:done ticket', 'status' => 'published', 'views' => 1]);
    Post::create(['title' => 'done', 'status' => 'published', 'views' => 1]);

    $filters = AllowedFilter::operators('title', [RequestedOperator::Not], partialByDefault: true);

    expect(matchedTitles(operatorBuilder('/?filter[title]=contains:release')->allowedFilters($filters)))
        ->toBe(['Alpha release', 'Beta release']);

    expect(matchedTitles(operatorBuilder('/?filter[title]='.urlencode('is:done'))->allowedFilters($filters)))
        ->toBe(['is:done ticket']);
});
