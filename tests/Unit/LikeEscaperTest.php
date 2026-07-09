<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Support\LikeEscaper;

it('escapes like wildcards and the escape character', function (): void {
    expect(LikeEscaper::escape('100%'))->toBe('100\\%')
        ->and(LikeEscaper::escape('a_b'))->toBe('a\\_b')
        ->and(LikeEscaper::escape('c\\d'))->toBe('c\\\\d');
});

it('escapes the backslash before the wildcards to avoid double-unescaping', function (): void {
    expect(LikeEscaper::escape('\\%'))->toBe('\\\\\\%');
});

it('leaves plain strings untouched', function (): void {
    expect(LikeEscaper::escape('hello world'))->toBe('hello world');
});
