<?php

declare(strict_types=1);

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
