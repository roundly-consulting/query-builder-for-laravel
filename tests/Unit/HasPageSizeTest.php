<?php

declare(strict_types=1);

use RoundlyConsulting\QueryBuilder\Tests\Support\Requests\ListRequest;

it('returns the default page size when per_page is absent', function (): void {
    expect(ListRequest::create('/')->perPage())->toBe(20);
});

it('returns the requested page size when in range', function (): void {
    expect(ListRequest::create('/?per_page=50')->perPage())->toBe(50);
});

it('hard-caps the page size at the configured max as defence in depth', function (): void {
    expect(ListRequest::create('/?per_page=500')->perPage())->toBe(100);
});

it('falls back to the default for a below-one page size', function (): void {
    expect(ListRequest::create('/?per_page=0')->perPage())->toBe(20);
});

it('builds validation rules bounded by the configured max', function (): void {
    expect(ListRequest::create('/')->pageSizeRules())
        ->toBe(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
});

it('respects configured pagination overrides', function (): void {
    config()->set('query-builder.pagination.per_page_name', 'limit');
    config()->set('query-builder.pagination.default_per_page', 15);
    config()->set('query-builder.pagination.max_per_page', 40);

    expect(ListRequest::create('/')->perPage())->toBe(15)
        ->and(ListRequest::create('/?limit=999')->perPage())->toBe(40)
        ->and(ListRequest::create('/')->pageSizeRules())
        ->toBe(['limit' => ['nullable', 'integer', 'min:1', 'max:40']]);
});
