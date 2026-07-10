<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Support\KeyList;

it('joins a short list unchanged', function (): void {
    expect(KeyList::summarize(['a', 'b', 'c']))->toBe('a, b, c');
});

it('caps the number of reflected keys and reports the remainder', function (): void {
    expect(KeyList::summarize(['a', 'b', 'c', 'd', 'e', 'f', 'g']))
        ->toBe('a, b, c, d, e …and 2 more');
});

it('truncates an over-long key', function (): void {
    $key = str_repeat('x', 100);

    expect(KeyList::summarize([$key]))->toBe(str_repeat('x', 64).'…');
});

it('honours custom limit and length', function (): void {
    expect(KeyList::summarize(['abcd', 'efgh', 'ijkl'], limit: 2, maxLength: 2))
        ->toBe('ab…, ef… …and 1 more');
});
