<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Enums;

use RoundlyConsulting\Enums\Helpers;

enum SortDirection: string
{
    use Helpers;

    case Ascending = 'asc';
    case Descending = 'desc';

    /**
     * Resolve the direction from a sort token: a leading `-` means descending.
     */
    public static function fromToken(string $token): self
    {
        return str_starts_with($token, '-') ? self::Descending : self::Ascending;
    }
}
