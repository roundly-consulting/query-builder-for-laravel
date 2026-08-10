<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Concerns\DeclaresEqualityOperators;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\DataTransferObjects\RequestedFilterValues;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;

/**
 * Membership in a JSON ARRAY column — the shape a `tags` column has.
 *
 * `where('tags', $tag)` is wrong for such a column (it compares the whole document) and
 * `like '%tag%'` is wrong twice over (it matches `release` inside `pre-release`, and it
 * matches a value that happens to appear in another key). `whereJsonContains` asks the
 * question the column actually answers, through the driver's own JSON support.
 *
 * The multi-value readings mirror the rest of the package: several values are ORed
 * ("tagged any of these") and their negation is ANDed ("tagged none of these"). A row whose
 * column is NULL matches a negation — "not tagged release" plainly covers a row with no
 * tags at all, the same reading {@see NotEqualFilter} takes for a scalar column.
 */
final readonly class JsonContainsOperatorFilter implements Filter
{
    use DeclaresEqualityOperators;

    /** @var list<RequestedOperator> */
    private array $allowed;

    /**
     * @param  list<RequestedOperator>  $operators  what the client may ask for, beyond the
     *                                              implicit `is`
     */
    public function __construct(array $operators = [RequestedOperator::Not])
    {
        $this->allowed = $this->equalityOperators($operators, 'JsonContainsOperatorFilter');
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        $parsed = RequestedFilterValues::parse($value, $this->allowed);

        if ($parsed->values === []) {
            return;
        }

        if ($parsed->negated()) {
            $query->where(function (Builder $inner) use ($parsed, $property): void {
                foreach ($parsed->values as $item) {
                    $inner->where(function (Builder $clause) use ($item, $property): void {
                        $clause->whereJsonDoesntContain($property, $item)->orWhereNull($property);
                    });
                }
            });

            return;
        }

        $query->where(function (Builder $inner) use ($parsed, $property): void {
            foreach ($parsed->values as $item) {
                $inner->orWhereJsonContains($property, $item);
            }
        });
    }
}
