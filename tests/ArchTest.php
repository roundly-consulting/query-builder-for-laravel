<?php

declare(strict_types=1);

// Allow-listing the permitted vendor roots (Laravel/Symfony, our own Enums helper,
// PHP built-ins) bans every other third-party vendor implicitly — none is named.
arch('src only uses allowed vendor roots')
    ->expect('RoundlyConsulting\QueryBuilder')
    ->toOnlyUse([
        'RoundlyConsulting\QueryBuilder',
        'RoundlyConsulting\Enums',
        'RoundlyConsulting\PackageToolkit',
        'Illuminate',
        'Symfony\Component\HttpKernel\Exception\HttpException',
        'Closure',
        // native helpers used unqualified
        'config',
        'config_path',
        'request',
        'trans',
        'value',
    ]);

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
