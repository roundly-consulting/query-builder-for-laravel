<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use RoundlyConsulting\QueryBuilder\QueryBuilder;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;
use RoundlyConsulting\QueryBuilder\Tests\Support\Requests\ListRequest;

beforeEach(function (): void {
    foreach (range(1, 5) as $i) {
        Post::create(['title' => "Post {$i}"]);
    }
});

function requestWith(array $query): Request
{
    $request = Request::create('/posts', 'GET', $query);

    app()->instance('request', $request);
    Paginator::currentPageResolver(fn (string $pageName = 'page'): int => (int) $request->query($pageName, 1));

    return $request;
}

it('paginates with the framework default page name out of the box', function (): void {
    requestWith([]);

    $page = QueryBuilder::for(Post::class)->paginate(2);

    expect($page)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($page->getPageName())->toBe('page');
});

it('paginates with the configured page name', function (): void {
    config()->set('query-builder.pagination.page_name', 'p');
    requestWith(['p' => 3]);

    $page = QueryBuilder::for(Post::class)->paginate(2);

    expect($page->getPageName())->toBe('p')
        ->and($page->currentPage())->toBe(3)
        ->and($page->url(2))->toContain('p=2');
});

it('honours the configured page name for simplePaginate too', function (): void {
    config()->set('query-builder.pagination.page_name', 'p');
    requestWith(['p' => 2]);

    $page = QueryBuilder::for(Post::class)->simplePaginate(2);

    expect($page->getPageName())->toBe('p')
        ->and($page->currentPage())->toBe(2);
});

it('lets a caller name the page itself, positionally', function (): void {
    config()->set('query-builder.pagination.page_name', 'p');
    requestWith([]);

    $page = QueryBuilder::for(Post::class)->paginate(2, ['*'], 'cursor');

    expect($page->getPageName())->toBe('cursor');
});

it('lets a caller name the page itself, by name', function (): void {
    config()->set('query-builder.pagination.page_name', 'p');
    requestWith([]);

    $page = QueryBuilder::for(Post::class)->paginate(2, pageName: 'cursor');

    expect($page->getPageName())->toBe('cursor');
});

it('still applies the allow-list when paginating', function (): void {
    requestWith(['filter' => ['title' => 'Post 1']]);

    $page = QueryBuilder::for(Post::class)
        ->allowedFilters('title')
        ->paginate(10);

    expect($page->total())->toBe(1);
});

it('exposes the configured page name to a form request', function (): void {
    config()->set('query-builder.pagination.page_name', 'p');

    $request = ListRequest::create('/posts', 'GET');

    expect($request->pageName())->toBe('p');
});

it('exposes the default page name to a form request', function (): void {
    $request = ListRequest::create('/posts', 'GET');

    expect($request->pageName())->toBe('page');
});
