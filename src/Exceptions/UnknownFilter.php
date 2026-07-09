<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class UnknownFilter extends HttpException implements QueryBuilderException
{
    /**
     * @param  list<string>  $unknown
     * @param  list<string>  $allowed
     */
    public static function make(array $unknown, array $allowed): self
    {
        $message = (string) trans('query-builder::errors.unknown_filter', [
            'unknown' => implode(', ', $unknown),
            'allowed' => implode(', ', $allowed),
        ]);

        return new self(400, $message);
    }
}
