<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\DataTransferObjects;

use RoundlyConsulting\QueryBuilder\Enums\SortDirection;

final readonly class SortParameter
{
    public function __construct(
        public string $property,
        public SortDirection $direction,
    ) {}
}
