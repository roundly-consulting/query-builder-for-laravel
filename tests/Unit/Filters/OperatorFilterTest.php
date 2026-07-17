<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Enums\FilterOperator;
use RoundlyConsulting\QueryBuilder\Filters\OperatorFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

beforeEach(function (): void {
    Post::create(['title' => 'Low', 'views' => 5]);
    Post::create(['title' => 'Mid', 'views' => 10]);
    Post::create(['title' => 'High', 'views' => 20]);
});

it('applies each operator with a bound scalar value', function (FilterOperator $operator, array $expected): void {
    $query = Post::query();
    (new OperatorFilter($operator))->apply($query, 10, 'views');

    expect($query->getBindings())->toBe([10])
        ->and($query->toSql())->toContain(wrapped('views').' '.$operator->value.' ?')
        ->and($query->pluck('title')->all())->toBe($expected);
})->with([
    'gte' => [FilterOperator::GreaterThanOrEqual, ['Mid', 'High']],
    'gt' => [FilterOperator::GreaterThan, ['High']],
    'lte' => [FilterOperator::LessThanOrEqual, ['Low', 'Mid']],
    'lt' => [FilterOperator::LessThan, ['Low']],
    'eq' => [FilterOperator::Equal, ['Mid']],
    'neq' => [FilterOperator::NotEqual, ['Low', 'High']],
]);

it('groups an array value into an OR of the comparison', function (): void {
    $query = Post::query();
    (new OperatorFilter(FilterOperator::GreaterThanOrEqual))->apply($query, [5, 20], 'views');

    expect($query->getBindings())->toBe([5, 20])
        ->and($query->toSql())->toContain('('.wrapped('views').' >= ? or '.wrapped('views').' >= ?)')
        ->and($query->pluck('title')->all())->toBe(['Low', 'Mid', 'High']);
});
