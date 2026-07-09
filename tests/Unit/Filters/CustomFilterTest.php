<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\QueryBuilder\AllowedFilter;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Filters\EvenViewsFilter;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;

it('runs a host-defined custom filter through the pipeline', function (): void {
    Post::create(['title' => 'A', 'views' => 2]);
    Post::create(['title' => 'B', 'views' => 3]);
    Post::create(['title' => 'C', 'views' => 4]);

    $query = Post::query();
    AllowedFilter::custom('even', new EvenViewsFilter)->apply($query, '1');

    expect($query->pluck('title')->all())->toBe(['A', 'C']);
});

it('passes the query, value and property to the custom filter', function (): void {
    $received = new stdClass;

    $filter = new class($received) implements Filter
    {
        public function __construct(private readonly stdClass $received) {}

        public function apply(Builder $query, mixed $value, string $property): void
        {
            $this->received->value = $value;
            $this->received->property = $property;
        }
    };

    AllowedFilter::custom('thing', $filter, 'internal_col')->apply(Post::query(), 'v');

    expect($received->value)->toBe('v')
        ->and($received->property)->toBe('internal_col');
});
