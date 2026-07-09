<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\QueryBuilder\Filters\CallbackFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

it('invokes the closure with the query, value and property', function (): void {
    Post::create(['title' => 'A', 'views' => 1]);
    Post::create(['title' => 'B', 'views' => 9]);

    $received = [];

    $filter = new CallbackFilter(function (Builder $query, mixed $value, string $property) use (&$received): void {
        $received = [$value, $property];
        $query->where('views', '>', $value);
    });

    $query = Post::query();
    $filter->apply($query, 5, 'views');

    expect($received)->toBe([5, 'views'])
        ->and($query->pluck('title')->all())->toBe(['B']);
});
