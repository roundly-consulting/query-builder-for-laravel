<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownFilter;
use RoundlyConsulting\QueryBuilder\Exceptions\UnknownSort;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

beforeEach(function (): void {
    Route::get('/qb-posts', fn (Request $request) => QueryBuilder::for(Post::class, $request)
        ->allowedFilters('status')
        ->allowedSorts('views')
        ->get());
});

it('returns a 400 for an unknown filter key', function (): void {
    $this->getJson('/qb-posts?filter[bogus]=1')
        ->assertStatus(400)
        ->assertJson(['message' => 'Requested filter(s) `bogus` are not allowed. Allowed filter(s) are `status`.']);
});

it('returns a 400 when known and unknown filters are mixed', function (): void {
    $this->getJson('/qb-posts?filter[status]=published&filter[bogus]=1')
        ->assertStatus(400);
});

it('returns a 400 for an unknown sort key', function (): void {
    $this->getJson('/qb-posts?sort=-bogus')
        ->assertStatus(400)
        ->assertJson(['message' => 'Requested sort(s) `bogus` are not allowed. Allowed sort(s) are `views`.']);
});

it('drops unknown filters silently in ignore mode', function (): void {
    config()->set('query-builder.mode.unknown_filter', 'ignore');
    Post::create(['title' => 'A', 'status' => 'published']);

    $this->getJson('/qb-posts?filter[bogus]=1&filter[status]=published')
        ->assertStatus(200)
        ->assertJsonCount(1);
});

it('drops unknown sorts silently in ignore mode', function (): void {
    config()->set('query-builder.mode.unknown_sort', 'ignore');
    Post::create(['title' => 'A']);

    $this->getJson('/qb-posts?sort=-bogus')
        ->assertStatus(200)
        ->assertJsonCount(1);
});

it('falls back to reject when the unknown-filter mode config is invalid', function (): void {
    config()->set('query-builder.mode.unknown_filter', 'bogus');

    $this->getJson('/qb-posts?filter[bogus]=1')
        ->assertStatus(400);
});

it('falls back to reject when the unknown-sort mode config is invalid', function (): void {
    config()->set('query-builder.mode.unknown_sort', 'bogus');

    $this->getJson('/qb-posts?sort=-bogus')
        ->assertStatus(400);
});

it('throws an http exception carrying a 400 status', function (): void {
    $throw = fn () => QueryBuilder::for(Post::class, Request::create('/?filter[bogus]=1'))
        ->allowedFilters('status')
        ->get();

    try {
        $throw();
        $this->fail('Expected UnknownFilter to be thrown.');
    } catch (UnknownFilter $exception) {
        expect($exception)->toBeInstanceOf(HttpExceptionInterface::class)
            ->and($exception->getStatusCode())->toBe(400);
    }
});

it('throws UnknownSort for an un-allow-listed sort property', function (): void {
    QueryBuilder::for(Post::class, Request::create('/?sort=name'))
        ->allowedSorts('views')
        ->get();
})->throws(UnknownSort::class);

it('translates the error message for the active locale', function (): void {
    app('translator')->addLines([
        'errors.unknown_filter' => 'Nope: :unknown (allowed :allowed)',
    ], 'xx', 'query-builder');

    app()->setLocale('xx');

    try {
        QueryBuilder::for(Post::class, Request::create('/?filter[bogus]=1'))
            ->allowedFilters('status')
            ->get();
        $this->fail('Expected UnknownFilter to be thrown.');
    } catch (UnknownFilter $exception) {
        expect($exception->getMessage())->toBe('Nope: bogus (allowed status)');
    }
});
