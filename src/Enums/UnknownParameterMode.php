<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Enums;

use RoundlyConsulting\Enums\Helpers;

enum UnknownParameterMode: string
{
    use Helpers;

    // Throw UnknownFilter/UnknownSort → HTTP 400.
    case Reject = 'reject';

    // Silently drop the un-allow-listed key.
    case Ignore = 'ignore';
}
