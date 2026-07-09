<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use RoundlyConsulting\QueryBuilder\Tests\Support\Requests\ListRequest;

beforeEach(function (): void {
    Route::get('/qb-page', fn (ListRequest $request) => ['per_page' => $request->perPage()]);
});

it('resolves the default page size for a valid empty request', function (): void {
    $this->getJson('/qb-page')
        ->assertStatus(200)
        ->assertJson(['per_page' => 20]);
});

it('resolves an in-range page size', function (): void {
    $this->getJson('/qb-page?per_page=50')
        ->assertStatus(200)
        ->assertJson(['per_page' => 50]);
});

it('rejects a page size above the cap with a 422', function (): void {
    $this->getJson('/qb-page?per_page=101')
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page');
});

it('rejects a below-one page size with a 422', function (): void {
    $this->getJson('/qb-page?per_page=0')->assertStatus(422);
});

it('rejects a non-integer page size with a 422', function (): void {
    $this->getJson('/qb-page?per_page=abc')->assertStatus(422);
});
