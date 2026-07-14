<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Support\LikeEscaper;

// The package used to ship its own LikeEscaper; it now uses the toolkit's.
// These pins were the local escaper's contract and they still hold byte for
// byte. Escaping is correctness-critical — a changed escape character or a
// missed wildcard is a LIKE-injection — so the adopted behaviour is pinned
// here rather than trusted.

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

it('escapes a lone escape character', function (): void {
    expect(LikeEscaper::escape('\\'))->toBe('\\\\');
});

it('escapes every wildcard in a wildcard-only value', function (): void {
    expect(LikeEscaper::escape('%_%'))->toBe('\\%\\_\\%');
});

it('is byte-safe on multi-byte input', function (): void {
    expect(LikeEscaper::escape('żółw 漢字 café'))->toBe('żółw 漢字 café')
        ->and(LikeEscaper::escape('漢%字_ç'))->toBe('漢\\%字\\_ç')
        ->and(LikeEscaper::escape('😀%'))->toBe('😀\\%');
});
