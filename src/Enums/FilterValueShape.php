<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Enums;

use Illuminate\Support\Str;
use RoundlyConsulting\Enums\Helpers;

/**
 * What a filter's values have to LOOK like before they are allowed near the database.
 *
 * This is a **500 guard**, not a validation nicety. Postgres rejects a value of the wrong
 * type outright — `invalid input syntax for type uuid`, `... for type bigint` — rather
 * than simply not matching it, so a filter that forwards request text straight to a typed
 * column turns `filter[project]=garbage` into a request-triggerable 500 on an
 * authenticated endpoint. (MySQL and SQLite coerce instead, which is its own surprise: a
 * non-numeric string becomes `0` and quietly matches row zero.)
 *
 * A value that does not match its column's shape can match no row, so the filter answers
 * with an empty result — which is exactly what "no row has that id" means anyway.
 *
 * The shape is declared per filter, at the call site, because only the call site knows the
 * column: one id column is a uuid, the next is a bigint, and a third holds a service name.
 * Defaulting to {@see self::Text} is deliberate — it guards nothing, which is the correct
 * behaviour for a column that genuinely accepts any string, and it keeps every existing
 * filter behaving exactly as it did.
 */
enum FilterValueShape: string
{
    use Helpers;

    /** Any string — an enum value, a slug, a name. Guards nothing. */
    case Text = 'text';

    /** A uuid primary key. */
    case Uuid = 'uuid';

    /** An integer id (a bigint column, typically a foreign or cross-service key). */
    case Id = 'id';

    public function matches(string $value): bool
    {
        return match ($this) {
            self::Text => true,
            self::Uuid => Str::isUuid($value),
            self::Id => ctype_digit($value),
        };
    }
}
