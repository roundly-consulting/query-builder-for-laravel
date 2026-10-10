<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;

/**
 * `FilterValueShape`, driven from the table this package shares with its Node twin
 * (`shape-cases.json`). Node's `matchesShape()` answers the same cases from its byte-identical
 * copy, so a shape rule cannot drift on one side only — `-0` and a padded `' 5'` included.
 */
it('matches every shared shape case', function (array $case): void {
    $shape = FilterValueShape::from($case['shape']);

    expect($shape->matches($case['value']))->toBe($case['matches']);

    if (array_key_exists('cast', $case)) {
        expect($shape->cast($case['value']))->toBe($case['cast']);
    }
})->with(function (): array {
    /** @var list<array{shape: string, value: string, matches: bool, cast?: bool}> $cases */
    $cases = contract('shape-cases')['cases'];

    $named = [];
    foreach ($cases as $index => $case) {
        $named[sprintf('#%d %s %s', $index, $case['shape'], json_encode($case['value']))] = [$case];
    }

    return $named;
});
