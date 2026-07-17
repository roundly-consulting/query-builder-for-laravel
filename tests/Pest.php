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
