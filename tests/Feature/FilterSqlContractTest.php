<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\QueryBuilder\AllowedFilter;
use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownFilter;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownSort;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\ParityItem;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * One URL, the same rows, on both stacks: `filter-sql-cases.json`, shared with the Node twin
 * (whose `sqlFilters()` + `guardListQuery()` answer the same cases from a byte-identical copy
 * on its own real-Postgres leg).
 *
 * The fixture is the reference for both halves, so every case runs through the real
 * `QueryBuilder` against a real engine: SQL text is not compared, only the rows it returns.
 * Postgres only, because that is the engine the twin runs and the one where the shapes earn
 * their keep — a malformed uuid or an out-of-range id is a driver error there, not a miss.
 */
beforeEach(function (): void {
    if (DriverMatrix::driver() !== 'pgsql') {
        $this->markTestSkipped('postgres only: the shared filter fixture is a Postgres contract');
    }

    /** @var array{table: string, columns: list<array{name: string, type: string, nullable: bool}>, rows: list<array<string, mixed>>} $fixture */
    $fixture = contract('filter-sql-cases');

    Schema::dropIfExists($fixture['table']);
    Schema::create($fixture['table'], function (Blueprint $table) use ($fixture): void {
        foreach ($fixture['columns'] as $column) {
            $definition = match ($column['type']) {
                'bigint' => $table->bigInteger($column['name']),
                'string' => $table->string($column['name']),
                'boolean' => $table->boolean($column['name']),
                'uuid' => $table->uuid($column['name']),
            };

            $definition->nullable($column['nullable']);
        }
    });

    DB::table($fixture['table'])->insert($fixture['rows']);
});

afterEach(function (): void {
    if (DriverMatrix::driver() === 'pgsql') {
        Schema::dropIfExists('parity_items');
    }
});

/**
 * The fixture's declarations, as the allow-list a host would write.
 *
 * @return list<AllowedFilter>
 */
function parityFilters(): array
{
    /** @var array<string, array{column: string, operators: list<string>, shape: string, nullable: bool}> $declared */
    $declared = contract('filter-sql-cases')['filters'];

    $filters = [];
    foreach ($declared as $name => $filter) {
        $shape = FilterValueShape::from($filter['shape']);
        $operators = array_map(RequestedOperator::from(...), $filter['operators']);

        $filters[] = $filter['nullable']
            ? AllowedFilter::nullable($name, $filter['column'], $shape, $operators)
            : AllowedFilter::operators($name, $operators, $filter['column'], shape: $shape);
    }

    return $filters;
}

it('answers every shared filter case with the same rows', function (array $case): void {
    /** @var array{sorts: list<string>, default_sort: string} $fixture */
    $fixture = contract('filter-sql-cases');

    $run = static fn (): array => QueryBuilder::for(ParityItem::class, Request::create('/?'.$case['query']))
        ->allowedFilters(...parityFilters())
        ->allowedSorts(...$fixture['sorts'])
        ->defaultSort($fixture['default_sort'])
        ->getEloquentBuilder()
        ->orderBy('id')
        ->pluck('id')
        ->map(static fn (mixed $id): int => (int) $id)
        ->all();

    if (! array_key_exists('error', $case)) {
        expect($run())->toBe($case['ids']);

        return;
    }

    $thrown = null;
    try {
        $run();
    } catch (UnknownFilter|UnknownSort $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf($case['error'] === 'unknown_filter' ? UnknownFilter::class : UnknownSort::class)
        ->and($thrown?->getStatusCode())->toBe(400)
        ->and($thrown?->getMessage())->toBe($case['message']);
})->with(function (): array {
    /** @var list<array{name: string, query: string, ids?: list<int>, error?: string, message?: string}> $cases */
    $cases = contract('filter-sql-cases')['cases'];

    return array_combine(array_column($cases, 'name'), array_map(static fn (array $case): array => [$case], $cases));
});
