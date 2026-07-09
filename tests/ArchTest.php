<?php

declare(strict_types=1);

arch('src does not depend on acme')
    ->expect('RoundlyConsulting\QueryBuilder')
    ->not->toUse('Acme');

arch('src declares strict types')
    ->expect('RoundlyConsulting\QueryBuilder')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('RoundlyConsulting\QueryBuilder\Contracts')
    ->toBeInterfaces();

arch('enums are enums')
    ->expect('RoundlyConsulting\QueryBuilder\Enums')
    ->toBeEnums();
