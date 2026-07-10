<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Support;

final class LikeEscaper
{
    /**
     * Escape the LIKE wildcards `%` and `_` (and the escape char `\`) in user
     * input so a filter value can never widen the match beyond a literal
     * substring. The escaped string is meant to be wrapped in `%...%` by the
     * caller and matched with an explicit `LIKE ? ESCAPE '\'` clause. The
     * explicit clause is required: SQLite has no default escape character, so
     * without it the escaping backslashes stay literal and the wildcards live.
     */
    public static function escape(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value,
        );
    }
}
