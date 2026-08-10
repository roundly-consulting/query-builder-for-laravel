<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\DataTransferObjects\RequestedFilterValues;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;

/**
 * The filter wire, driven from the table this package SHARES with its Node twin.
 *
 * `fixtures/wire-cases.json` is byte-identical to the copy in `query-builder-for-node` (and
 * to the frontend kit's). Two independent implementations of one URL grammar drift silently
 * — a client writes the same link whichever stack answers it — and prose in a docblock is
 * not a thing a build can fail on. This is.
 *
 * If a case here cannot be made to pass on both sides, the wire has forked, and that is the
 * bug rather than the test.
 */
function wireCases(): array
{
    $path = __DIR__.'/../../fixtures/wire-cases.json';
    $fixture = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    return array_map(
        static fn (array $case): array => [$case['name'], $case],
        $fixture['cases'],
    );
}

it('parses the shared wire cases identically to the node half', function (string $name, array $case): void {
    $allowed = array_map(
        static fn (string $token): RequestedOperator => RequestedOperator::from($token),
        $case['offered'],
    );

    // The comma split is `QueryBuilderRequest`'s job in a real request; applying it here is
    // what puts both halves at the same point in the pipeline.
    $raw = str_contains($case['raw'], ',') ? explode(',', $case['raw']) : $case['raw'];

    $parsed = RequestedFilterValues::parse($raw, $allowed);

    expect(($parsed->operator ?? RequestedOperator::Is)->value)->toBe($case['operator'], $name)
        ->and($parsed->values)->toBe($case['values'], $name);
})->with(wireCases());
