<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Support;

/**
 * The wire value that means "this is unset".
 *
 * A nullable column and an empty relation have no other way to say so in a URL: an empty
 * `filter[project]=` is indistinguishable from the parameter being absent, and there is no
 * spelling of NULL a query string can carry. So "unset" is a reserved literal value, and
 * this is where it is spelled — once, so the filters that honour it and the clients that
 * write it cannot drift.
 *
 * It is a DEFAULT, not a reservation: a column that legitimately stores the string `none`
 * passes its own sentinel to the filter (or opts out of one entirely).
 */
final class FilterSentinel
{
    public const NONE = 'none';
}
