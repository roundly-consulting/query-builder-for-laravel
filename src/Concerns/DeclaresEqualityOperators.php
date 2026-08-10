<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Concerns;

use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\Exceptions\UnsupportedOperator;

/**
 * The operator allow-list of a filter that answers exactly two questions: "is one of
 * these" and "is none of these".
 *
 * Shared by the three filters built on that shape ({@see NullableOperatorFilter},
 * {@see RelationOperatorFilter}, {@see JsonContainsOperatorFilter}) so the rule that `is`
 * is always nameable — and the refusal of anything they cannot perform — is written once.
 * Three copies of it is three places for it to drift.
 */
trait DeclaresEqualityOperators
{
    /**
     * @param  list<RequestedOperator>  $operators
     * @return list<RequestedOperator>
     */
    private function equalityOperators(array $operators, string $filter): array
    {
        foreach ($operators as $operator) {
            if ($operator !== RequestedOperator::Is && $operator !== RequestedOperator::Not) {
                throw UnsupportedOperator::make(
                    $operator,
                    [RequestedOperator::Is, RequestedOperator::Not],
                    $filter,
                );
            }
        }

        // `is` is always nameable: a client that switched a chip has to be able to switch
        // back. `array_unique` may reorder; only membership is ever read.
        return array_values(array_unique([RequestedOperator::Is, ...$operators], SORT_REGULAR));
    }
}
