<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\Rules\FilterValue;
use RoundlyConsulting\QueryBuilder\Support\FilterSentinel;

function validateFilter(mixed $value, array|string|object $rules, ?array $operators = null): array
{
    $validator = Validator::make(
        ['filter' => ['level' => $value]],
        ['filter.level' => ['nullable', new FilterValue($rules, $operators)]],
    );

    /** @var list<string> */
    return $validator->errors()->get('filter.level');
}

it('accepts a bare value its rules allow', function (): void {
    expect(validateFilter('error', Rule::in(['error', 'debug'])))->toBe([]);
});

it('accepts the same value behind an operator the filter declared', function (): void {
    // The whole point: `not:debug` is not one of the allowed levels, but the VALUE is.
    expect(validateFilter('not:debug', Rule::in(['error', 'debug'])))->toBe([])
        ->and(validateFilter('gte:500', 'integer', [RequestedOperator::GreaterThanOrEqual]))->toBe([]);
});

it('still rejects a value its rules disallow', function (): void {
    expect(validateFilter('not:nonsense', Rule::in(['error', 'debug'])))->not->toBe([])
        ->and(validateFilter('gte:garbage', 'integer', [RequestedOperator::GreaterThanOrEqual]))->not->toBe([]);
});

it('reports the endpoint own message, not a generic one', function (): void {
    $errors = validateFilter('gte:garbage', 'integer', [RequestedOperator::GreaterThanOrEqual]);

    expect($errors[0])->toContain('integer');
});

it('validates every element of a multi value', function (): void {
    expect(validateFilter('not:error,debug', Rule::in(['error', 'debug'])))->toBe([])
        ->and(validateFilter('not:error,nonsense', Rule::in(['error', 'debug'])))->not->toBe([]);
});

it('validates a value whole when the prefix is not an operator it strips', function (): void {
    // `is` is an operator, but not one this filter offers — so the value is the text.
    expect(validateFilter('is:error', Rule::in(['error']), [RequestedOperator::Not]))->not->toBe([])
        ->and(validateFilter('https://example.test', 'url'))->toBe([]);
});

it('lets a sentinel through, because the filter answers it rather than the column', function (): void {
    // Without this the endpoint 422s on `none` — the one value a nullable filter exists to
    // answer — while its own controller advertises it.
    $validator = Validator::make(
        ['filter' => ['project' => 'none']],
        ['filter.project' => ['nullable', new FilterValue('uuid', sentinels: [FilterSentinel::NONE])]],
    );

    expect($validator->errors()->get('filter.project'))->toBe([]);
});

it('still rejects a non-sentinel value of the wrong shape', function (): void {
    $validator = Validator::make(
        ['filter' => ['project' => 'garbage']],
        ['filter.project' => ['nullable', new FilterValue('uuid', sentinels: [FilterSentinel::NONE])]],
    );

    expect($validator->errors()->get('filter.project'))->not->toBe([]);
});

it('validates the negated sentinel too', function (): void {
    $validator = Validator::make(
        ['filter' => ['project' => 'not:none']],
        ['filter.project' => ['nullable', new FilterValue('uuid', sentinels: [FilterSentinel::NONE])]],
    );

    expect($validator->errors()->get('filter.project'))->toBe([]);
});

it('defaults to the equality pair, so a comparison filter has to declare itself', function (): void {
    // `gte:` stripped by a rule the filter never offered would accept a value the filter
    // then treats as literal text — an empty list that looks like it worked.
    expect(validateFilter('gte:500', 'integer'))->not->toBe([])
        ->and(validateFilter('gte:500', 'integer', [RequestedOperator::GreaterThanOrEqual]))->toBe([]);
});

it('caps how many elements it validates', function (): void {
    config()->set('query-builder.limits.max_filter_values', 3);

    // Only the first three are validated; the request normaliser drops the rest anyway.
    expect(validateFilter('error,error,error,nonsense', Rule::in(['error'])))->toBe([]);
});
