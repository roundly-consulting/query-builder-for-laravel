<?php

declare(strict_types=1);

/**
 * The contract fixtures are copied byte-for-byte into query-builder-for-node and compared with
 * `cmp` (`./parity.sh`), so their bytes are part of the contract: one canonical encoding
 * (4-space pretty print, unescaped slashes and unicode, one trailing newline — what
 * `JSON.stringify(data, null, 4) + "\n"` also produces), never a hand-formatted variant.
 */
it('keeps every contract fixture in its canonical byte form', function (string $file): void {
    $bytes = (string) file_get_contents($file);
    $data = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);

    expect(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n")
        ->toBe($bytes);
})->with(function (): array {
    $files = glob(__DIR__.'/../Fixtures/contract/*.json') ?: [];

    return array_combine(array_map('basename', $files), $files);
});

it('ships exactly the query-builder contract set', function (): void {
    $names = array_map('basename', glob(__DIR__.'/../Fixtures/contract/*.json') ?: []);

    expect($names)->toBe([
        'filter-sql-cases.json',
        'shape-cases.json',
        'sort-cases.json',
    ]);
});
