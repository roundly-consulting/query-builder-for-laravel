<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\QueryBuilder\AllowedSort;
use RoundlyConsulting\QueryBuilder\Contracts\Sort;
use RoundlyConsulting\QueryBuilder\Enums\SortDirection;
use RoundlyConsulting\QueryBuilder\Tests\Support\Models\Post;
use RoundlyConsulting\QueryBuilder\Tests\Support\Sorts\TitleLengthSort;

it('runs a host-defined custom sort through the pipeline', function (): void {
    Post::create(['title' => 'aaa']);
    Post::create(['title' => 'a']);
    Post::create(['title' => 'aa']);

    $query = Post::query();
    AllowedSort::custom('length', new TitleLengthSort)->apply($query, SortDirection::Ascending);

    expect($query->pluck('title')->all())->toBe(['a', 'aa', 'aaa']);
});

it('passes the direction and property to the custom sort', function (): void {
    $received = new stdClass;

    $sort = new class($received) implements Sort
    {
        public function __construct(private readonly stdClass $received) {}

        public function apply(Builder $query, SortDirection $direction, string $property): void
        {
            $this->received->direction = $direction;
            $this->received->property = $property;
        }
    };

    AllowedSort::custom('thing', $sort, 'internal_col')->apply(Post::query(), SortDirection::Descending);

    expect($received->direction)->toBe(SortDirection::Descending)
        ->and($received->property)->toBe('internal_col');
});
