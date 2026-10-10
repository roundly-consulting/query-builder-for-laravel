<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Support\LikeEscaper;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\Support\LikeClause;

/**
 * The negation of {@see PartialFilter}: rows whose column does NOT contain the needle.
 *
 * Same escaping, same driver handling, same binding — the only differences are `not like`
 * and that multiple values are ANDed rather than ORed. That inversion is not a detail: a
 * grouped OR of negations matches almost every row (a value is always "not" one of two
 * things), so `ncontains:a,b` has to mean "contains neither", which is AND.
 *
 * **A NULL column does not match a `not contains`** in SQL, which is rarely what a person
 * filtering a list means — "not containing 'draft'" should include rows with no value at
 * all. `orWhereNull` restores that reading.
 *
 * **An empty needle is a no-op**, matching {@see PartialFilter} (where an empty value is a
 * documented match-all). Negating it naively would build `not like '%%'`, which is false
 * for every non-null row: one stray trailing comma from a UI joining a multi-select would
 * turn "exclude drafts" into "show only rows with no value", silently hiding the rest.
 */
final readonly class NotPartialFilter implements Filter
{
    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $values = array_values(array_filter(
            is_array($value) ? $value : [$value],
            static fn (mixed $item): bool => $item !== null && $item !== '',
        ));

        if ($values === []) {
            return;
        }

        // Raw so the explicit `ESCAPE '\'` clause is attached; the column is a
        // grammar-wrapped developer-supplied identifier, the needle and escape char are
        // bound. No request input reaches an identifier position.
        $condition = LikeClause::condition($query, $query->getQuery()->getGrammar()->wrap($property), negated: true);

        $query->where(function (Builder $query) use ($values, $condition, $property): void {
            foreach ($values as $item) {
                $needle = '%'.LikeEscaper::escape((string) $item).'%';

                $query->where(function (Builder $query) use ($condition, $needle, $property): void {
                    $query->whereRaw($condition, [$needle, LikeClause::ESCAPE])
                        ->orWhereNull($property);
                });
            }
        });
    }
}
