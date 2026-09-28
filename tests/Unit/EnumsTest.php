<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Enums\FilterOperator;
use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\Enums\SortDirection;
use RoundlyConsulting\QueryBuilder\Enums\UnknownParameterMode;

it('maps sort direction values to sql keywords', function (): void {
    expect(SortDirection::Ascending->value)->toBe('asc')
        ->and(SortDirection::Descending->value)->toBe('desc');
});

it('resolves sort direction from a token', function (): void {
    expect(SortDirection::fromToken('name'))->toBe(SortDirection::Ascending)
        ->and(SortDirection::fromToken('-name'))->toBe(SortDirection::Descending)
        ->and(SortDirection::fromToken('created_at'))->toBe(SortDirection::Ascending);
});

it('exposes the enums-for-laravel helper surface on sort direction', function (): void {
    expect(SortDirection::values()->all())->toBe(['asc', 'desc'])
        ->and(SortDirection::count())->toBe(2);
});

it('resolves the unknown-parameter mode from config strings', function (): void {
    expect(UnknownParameterMode::from('reject'))->toBe(UnknownParameterMode::Reject)
        ->and(UnknownParameterMode::from('ignore'))->toBe(UnknownParameterMode::Ignore);
});

it('rejects an invalid unknown-parameter mode value', function (): void {
    UnknownParameterMode::from('nonsense');
})->throws(ValueError::class);

it('backs each filter operator with its sql comparison', function (): void {
    expect(FilterOperator::values()->all())->toBe(['=', '!=', '>', '>=', '<', '<='])
        ->and(FilterOperator::GreaterThanOrEqual->value)->toBe('>=')
        ->and(FilterOperator::count())->toBe(6);
});

it('backs each requested operator with a url token, never sql', function (): void {
    // The whole point of the second enum: these values go in a URL, so a change here is a
    // wire change. `FilterOperator`'s values are SQL; none of these may be.
    expect(RequestedOperator::values()->all())
        ->toBe(['is', 'not', 'contains', 'ncontains', 'starts', 'gt', 'gte', 'lt', 'lte'])
        ->and(RequestedOperator::count())->toBe(9);
});

/*
|--------------------------------------------------------------------------
| RequestedOperator::split() — a public API for consumer callbacks
|--------------------------------------------------------------------------
|
| Documented as the parse an `AllowedFilter::callback()` should reuse rather than
| hand-roll, so it is pinned here independently of the filter that also calls it.
*/

it('splits a declared operator off the value', function (): void {
    $parsed = RequestedOperator::split('not:draft', [RequestedOperator::Not]);

    expect($parsed->operator)->toBe(RequestedOperator::Not)
        ->and($parsed->value)->toBe('draft');
});

it('answers a null operator when the request named none', function (): void {
    // `null` is not `Is`: only "named none" takes the caller's default, which is what lets
    // a search field default to `contains` without `is:` becoming a free extra token.
    $parsed = RequestedOperator::split('draft', [RequestedOperator::Not]);

    expect($parsed->operator)->toBeNull()
        ->and($parsed->value)->toBe('draft')
        ->and($parsed->operatorOr(RequestedOperator::Contains))->toBe(RequestedOperator::Contains);
});

it('treats an undeclared or unknown token as part of the value', function (): void {
    foreach (['contains:draft', 'nonsense:draft', '1=1 or 1:draft', 'is:draft'] as $raw) {
        $parsed = RequestedOperator::split($raw, [RequestedOperator::Not]);

        expect($parsed->operator)->toBeNull()
            ->and($parsed->value)->toBe($raw);
    }
});

it('only reads a token before the FIRST colon', function (): void {
    $parsed = RequestedOperator::split('https://example.test', RequestedOperator::cases());

    expect($parsed->operator)->toBeNull()
        ->and($parsed->value)->toBe('https://example.test');
});

it('rejects an id too large for the column it guards', function (): void {
    // A digit string is not the same question as a number a bigint can hold: Postgres
    // answers the second with `22003 value out of range`, which is the same 500 this shape
    // exists to prevent.
    expect(FilterValueShape::Id->matches('42'))->toBeTrue()
        ->and(FilterValueShape::Id->matches((string) PHP_INT_MAX))->toBeTrue()
        ->and(FilterValueShape::Id->matches('99999999999999999999999'))->toBeFalse()
        ->and(FilterValueShape::Id->matches('-1'))->toBeFalse()
        ->and(FilterValueShape::Id->matches('4.2'))->toBeFalse()
        ->and(FilterValueShape::Id->matches('garbage'))->toBeFalse();
});

it('accepts only the boolean spellings for a boolean column', function (): void {
    expect(FilterValueShape::Boolean->matches('true'))->toBeTrue()
        ->and(FilterValueShape::Boolean->matches('FALSE'))->toBeTrue()
        ->and(FilterValueShape::Boolean->matches('1'))->toBeTrue()
        ->and(FilterValueShape::Boolean->matches('0'))->toBeTrue()
        ->and(FilterValueShape::Boolean->matches('yes'))->toBeFalse()
        ->and(FilterValueShape::Boolean->matches('2'))->toBeFalse()
        ->and(FilterValueShape::Boolean->matches(''))->toBeFalse();
});

it('casts a boolean-shaped value to a real boolean and leaves every other shape alone', function (): void {
    expect(FilterValueShape::Boolean->cast('True'))->toBeTrue()
        ->and(FilterValueShape::Boolean->cast('1'))->toBeTrue()
        ->and(FilterValueShape::Boolean->cast('false'))->toBeFalse()
        ->and(FilterValueShape::Boolean->cast('0'))->toBeFalse()
        ->and(FilterValueShape::Text->cast('false'))->toBe('false')
        ->and(FilterValueShape::Id->cast('1'))->toBe('1');
});
