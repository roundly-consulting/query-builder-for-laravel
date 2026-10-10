<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\QueryBuilder\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * A column identifier quoted the way the **active connection's grammar** quotes it.
 *
 * Assertions on compiled SQL must not hard-code a quoting style. `"status" = ?` looks
 * portable and is not: double quotes are the sqlite/pgsql grammar, and MySQL quotes with
 * backticks (`` `status` = ? ``). Five such assertions across the filter and sort tests
 * passed for this package's whole life and went red the moment the mysql leg first ran —
 * 13 failures, none of them a package bug. The package was always right: it wraps every
 * identifier through `$grammar->wrap()`, which is exactly why it emits backticks on MySQL.
 *
 * So the test must ask the same grammar the code asks, and assert the *shape* of the SQL
 * rather than one engine's spelling of it.
 *
 * ```php
 * expect($query->toSql())->toContain(wrapped('status').' = ?');
 * ```
 */
function wrapped(string $column): string
{
    return DB::connection()->getQueryGrammar()->wrap($column);
}

/**
 * A canonical contract fixture from `tests/Fixtures/contract/`.
 *
 * These files are a contract shared with `query-builder-for-node`, which keeps byte-identical
 * copies as its goldens (`./parity.sh` cmp-checks the pair) and runs its own half against
 * them. Laravel is the reference: a case changes here first, then in the copy.
 *
 * @return array<string, mixed>
 */
function contract(string $name): array
{
    /** @var array<string, mixed> $data */
    $data = json_decode((string) file_get_contents(__DIR__.'/Fixtures/contract/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);

    return $data;
}
