<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\QueryBuilder\Concerns\DeclaresEqualityOperators;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;
use RoundlyConsulting\QueryBuilder\DataTransferObjects\RequestedFilterValues;
use RoundlyConsulting\QueryBuilder\Enums\FilterValueShape;
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
 *
 * **JSON membership is type-strict** on every engine: the text `"5"` is not the number `5`.
 * So the array's element type is declared as a {@see FilterValueShape}: the default `Text`
 * binds the values as text (a `tags` column), `Id` binds them as integers (a `label_ids`
 * column), `Boolean` as booleans, `Uuid` as text. Values are shape-checked first, exactly as
 * {@see NullableOperatorFilter} and {@see RelationOperatorFilter} do; when none fits, a
 * positive match matches nothing and a negation excludes nothing.
 */
final readonly class JsonContainsOperatorFilter implements Filter
{
    use DeclaresEqualityOperators;

    /** @var list<RequestedOperator> */
    private array $allowed;

    /**
     * @param  list<RequestedOperator>  $operators  what the client may ask for, beyond the
     *                                              implicit `is`
     * @param  FilterValueShape  $shape  what the array's elements are; `Id` binds integers
     */
    public function __construct(
        array $operators = [RequestedOperator::Not],
        private FilterValueShape $shape = FilterValueShape::Text,
    ) {
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

        $usable = $this->bindable(RequestedFilterValues::matching($parsed->values, $this->shape));

        if ($usable === []) {
            // No element can be a value of the wrong shape. Matching it is an empty result;
            // excluding it excludes nothing.
            if (! $parsed->negated()) {
                $query->whereIn($property, []);
            }

            return;
        }

        if ($parsed->negated()) {
            $query->where(function (Builder $inner) use ($usable, $property): void {
                foreach ($usable as $item) {
                    $inner->where(function (Builder $clause) use ($item, $property): void {
                        $clause->whereJsonDoesntContain($property, $item)->orWhereNull($property);
                    });
                }
            });

            return;
        }

        $query->where(function (Builder $inner) use ($usable, $property): void {
            foreach ($usable as $item) {
                $inner->orWhereJsonContains($property, $item);
            }
        });
    }

    /**
     * Each value as the array holds it: an `Id` is a JSON number, never the text the
     * request carried. A value that passed the `Id` check fits a PHP int.
     *
     * @param  list<bool|string>  $values
     * @return list<bool|int|string>
     */
    private function bindable(array $values): array
    {
        if ($this->shape !== FilterValueShape::Id) {
            return $values;
        }

        return array_map(static fn (bool|string $value): int => (int) $value, $values);
    }
}
