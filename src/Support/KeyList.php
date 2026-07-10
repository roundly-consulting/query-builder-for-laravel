<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Support;

final class KeyList
{
    /**
     * Render a list of request-supplied keys for an error message with the
     * reflected content bounded: at most `$limit` keys, each truncated to
     * `$maxLength` characters, with a "…and N more" suffix. Un-allow-listed
     * keys come straight off the wire, so this caps how much attacker-controlled
     * text can be reflected into a 400 response and the logs.
     *
     * @param  list<string>  $keys
     */
    public static function summarize(array $keys, int $limit = 5, int $maxLength = 64): string
    {
        $shown = array_slice($keys, 0, max(1, $limit));

        $truncated = array_map(
            static fn (string $key): string => mb_strlen($key) > $maxLength
                ? mb_substr($key, 0, $maxLength).'…'
                : $key,
            $shown,
        );

        $summary = implode(', ', $truncated);

        $remaining = count($keys) - count($shown);

        if ($remaining > 0) {
            $summary .= " …and {$remaining} more";
        }

        return $summary;
    }
}
