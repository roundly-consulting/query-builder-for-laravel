<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Exceptions;

use RoundlyConsulting\QueryBuilder\Support\KeyList;
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
            'unknown' => KeyList::summarize($unknown),
            'allowed' => implode(', ', $allowed),
        ]);

        return new self(400, $message);
    }
}
