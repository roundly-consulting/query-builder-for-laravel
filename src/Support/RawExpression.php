<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * A raw SQL fragment assembled from a developer-supplied, grammar-wrapped column
 * identifier (never request input). Unlike the framework's own `Expression`,
 * which is type-constrained to literal strings, this carries a runtime-built
 * string — the safety guarantee is upheld by the caller wrapping the identifier
 * through the grammar and binding every value, not by the string being literal.
 */
final readonly class RawExpression implements Expression
{
    public function __construct(
        private string $value,
    ) {}

    public function getValue(Grammar $grammar): string
    {
        return $this->value;
    }
}
