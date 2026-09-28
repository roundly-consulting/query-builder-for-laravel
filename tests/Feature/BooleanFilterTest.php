<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use RoundlyConsulting\QueryBuilder\AllowedFilter;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

/**
 * `true` / `false` on the wire are TEXT unless the filter says its column is boolean.
 *
 * They used to be turned into PHP booleans for every filter before any filter ran, which
 * broke every text filter that met the words: `filter[title]=false` became `LIKE '%%'` and
 * matched every row, `filter[title]=true` became `LIKE '%1%'`, and an exact
 * `filter[status]=false` compared the column with `0` — on MySQL that matches every
 * non-numeric string. Only the filters that opt in (`boolean()`, a
 * `FilterValueShape::Boolean`, or `booleans: true` on a scope / callback / custom filter)
 * now receive booleans.
 */
function boolTitles(string $uri, AllowedFilter ...$filters): array
{
    /** @var list<string> */
    return QueryBuilder::for(Post::class, Request::create($uri))
        ->allowedFilters(...$filters)
        ->get()
        ->pluck('title')
        ->sort()
        ->values()
        ->all();
}

beforeEach(function (): void {
    Post::create(['title' => 'Is it false?', 'status' => 'false', 'active' => false]);
    Post::create(['title' => 'Is it true?', 'status' => 'true', 'active' => true]);
    Post::create(['title' => 'Version 1.0', 'status' => 'draft', 'active' => true]);
});

/*
|--------------------------------------------------------------------------
| Text filters keep the text
|--------------------------------------------------------------------------
*/

it('searches a partial filter for the literal text false', function (): void {
    expect(boolTitles('/?filter[title]=false', AllowedFilter::partial('title')))
        ->toBe(['Is it false?']);
});

it('searches a partial filter for the literal text true', function (): void {
    expect(boolTitles('/?filter[title]=TRUE', AllowedFilter::partial('title')))
        ->toBe(['Is it true?']);
});

it('matches an exact text filter against the literal text false', function (): void {
    expect(boolTitles('/?filter[status]=false', AllowedFilter::exact('status')))
        ->toBe(['Is it false?']);
});

it('keeps true and false as text inside a comma list', function (): void {
    expect(boolTitles('/?filter[status]=true,false', AllowedFilter::exact('status')))
        ->toBe(['Is it false?', 'Is it true?']);
});

it('searches an operators() text field for the literal text', function (): void {
    expect(boolTitles('/?filter[title]=true', AllowedFilter::operators('title', [], partialByDefault: true)))
        ->toBe(['Is it true?']);
});

it('hands a scope that did not opt in the text as sent', function (): void {
    $received = null;

    QueryBuilder::for(Post::class, Request::create('/?filter[flag]=false'))
        ->allowedFilters(AllowedFilter::callback('flag', function (Builder $query, mixed $value) use (&$received): void {
            $received = $value;
        }))
        ->get();

    expect($received)->toBe('false');
});

/*
|--------------------------------------------------------------------------
| boolean()
|--------------------------------------------------------------------------
*/

it('matches a boolean column with true and false', function (): void {
    expect(boolTitles('/?filter[active]=true', AllowedFilter::boolean('active')))
        ->toBe(['Is it true?', 'Version 1.0'])
        ->and(boolTitles('/?filter[active]=false', AllowedFilter::boolean('active')))
        ->toBe(['Is it false?']);
});

it('reads 1, 0 and any letter case as booleans', function (string $raw, array $expected): void {
    expect(boolTitles('/?filter[active]='.$raw, AllowedFilter::boolean('active')))->toBe($expected);
})->with([
    '1' => ['1', ['Is it true?', 'Version 1.0']],
    '0' => ['0', ['Is it false?']],
    'TRUE' => ['TRUE', ['Is it true?', 'Version 1.0']],
    'False' => ['False', ['Is it false?']],
]);

it('maps a public boolean filter name to its column', function (): void {
    expect(boolTitles('/?filter[enabled]=false', AllowedFilter::boolean('enabled', 'active')))
        ->toBe(['Is it false?']);
});

it('matches nothing for a value a boolean column cannot hold', function (): void {
    // On Postgres `boolean = 'maybe'` is `invalid input syntax for type boolean` — a
    // request-triggerable 500. A value of the wrong shape answers empty instead.
    expect(boolTitles('/?filter[active]=maybe', AllowedFilter::boolean('active')))->toBe([]);
});

it('matches either value from a boolean comma list', function (): void {
    expect(boolTitles('/?filter[active]=true,false', AllowedFilter::boolean('active')))
        ->toBe(['Is it false?', 'Is it true?', 'Version 1.0']);
});

it('offers no operator on a plain boolean filter', function (): void {
    // `not:` was never declared, so it is part of the value — and `not:true` is no boolean.
    expect(boolTitles('/?filter[active]=not:true', AllowedFilter::boolean('active')))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| FilterValueShape::Boolean
|--------------------------------------------------------------------------
*/

it('negates a boolean-shaped operators() filter', function (): void {
    $filter = AllowedFilter::operators('active', [RequestedOperator::Not], shape: FilterValueShape::Boolean);

    expect(boolTitles('/?filter[active]=not:true', $filter))->toBe(['Is it false?'])
        ->and(boolTitles('/?filter[active]=false', $filter))->toBe(['Is it false?']);
});

it('refuses a partial operator on a boolean-shaped filter', function (): void {
    AllowedFilter::operators('active', [RequestedOperator::Contains], shape: FilterValueShape::Boolean);
})->throws(LogicException::class, 'A `boolean`-shaped filter cannot perform the `contains` operator');

it('matches a nullable boolean column through its sentinel and its values', function (): void {
    $filter = AllowedFilter::nullable('active', shape: FilterValueShape::Boolean);

    expect(boolTitles('/?filter[active]=false', $filter))->toBe(['Is it false?'])
        ->and(boolTitles('/?filter[active]=not:false', $filter))->toBe(['Is it true?', 'Version 1.0']);
});

/*
|--------------------------------------------------------------------------
| booleans: true on the host-code filters
|--------------------------------------------------------------------------
*/

it('hands a scope that opted in a real boolean', function (): void {
    $result = QueryBuilder::for(Post::class, Request::create('/?filter[published]=false'))
        ->allowedFilters(AllowedFilter::scope('published', booleans: true))
        ->get();

    // `scopePublished(bool $published)` — `false` is the drafts. Without the opt-in the
    // string 'false' is truthy and the scope would have answered the published rows.
    expect($result->pluck('title')->all())->toBe(['Version 1.0']);
});

it('hands a callback that opted in real booleans, list items too', function (): void {
    $received = [];

    QueryBuilder::for(Post::class, Request::create('/?filter[one]=TRUE&filter[many]=true,0,maybe'))
        ->allowedFilters(
            AllowedFilter::callback('one', function (Builder $query, mixed $value) use (&$received): void {
                $received['one'] = $value;
            }, booleans: true),
            AllowedFilter::callback('many', function (Builder $query, mixed $value) use (&$received): void {
                $received['many'] = $value;
            }, booleans: true),
        )
        ->get();

    // Only a boolean spelling becomes a boolean; anything else stays the text it was.
    expect($received)->toBe(['one' => true, 'many' => [true, false, 'maybe']]);
});

it('hands a custom filter that opted in a real boolean', function (): void {
    $filter = new class implements Filter
    {
        public mixed $received = null;

        public function apply(Builder $query, mixed $value, string $property): void
        {
            $this->received = $value;
        }
    };

    QueryBuilder::for(Post::class, Request::create('/?filter[x]=0'))
        ->allowedFilters(AllowedFilter::custom('x', $filter, booleans: true))
        ->get();

    expect($filter->received)->toBeFalse();
});
