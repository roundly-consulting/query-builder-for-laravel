<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownSort;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

/**
 * The `sort` grammar and the `max_sorts` cap, driven from the table this package shares with
 * its Node twin (`sort-cases.json`, read there by `requestedSorts()`).
 *
 * Both halves of the claim are checked through the real `QueryBuilder`: the sorts it applies
 * (in `ignore` mode, so an unknown key does not stop the run) and the keys `reject` mode
 * reports. Nothing is executed — the ORDER BY the builder holds is the answer.
 */
it('applies and rejects every shared sort case', function (array $case): void {
    $request = Request::create('/', 'GET', ['sort' => $case['raw']]);

    config()->set('query-builder.mode.unknown_sort', 'ignore');

    $orders = QueryBuilder::for(Post::class, $request)
        ->allowedSorts(...$case['offered'])
        ->getEloquentBuilder()
        ->getQuery()
        ->orders ?? [];

    expect(array_map(
        static fn (array $order): array => ['key' => $order['column'], 'desc' => $order['direction'] === 'desc'],
        $orders,
    ))->toBe($case['sorts']);

    config()->set('query-builder.mode.unknown_sort', 'reject');

    $thrown = null;
    try {
        QueryBuilder::for(Post::class, $request)->allowedSorts(...$case['offered'])->getEloquentBuilder();
    } catch (UnknownSort $e) {
        $thrown = $e->getMessage();
    }

    expect($thrown)->toBe($case['unknown'] === [] ? null : UnknownSort::make($case['unknown'], $case['offered'])->getMessage());
})->with(function (): array {
    /** @var list<array{name: string, raw: string, offered: list<string>, sorts: list<array{key: string, desc: bool}>, unknown: list<string>}> $cases */
    $cases = contract('sort-cases')['cases'];

    return array_combine(array_column($cases, 'name'), array_map(static fn (array $case): array => [$case], $cases));
});
