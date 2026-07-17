<?php

declare(strict_types=1);

use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * The presets replace query-builder's generic hand-written rules (strict types, debugging
 * leftovers); the bespoke rules below have no preset equivalent and are kept.
 */
ArchPresets::strictTypes('RoundlyConsulting\QueryBuilder');

/**
 * No exemptions: query-builder ships no config-swappable model and no documented subclassing
 * seam — a host extends it by *implementing* the Filter/Sort contracts, never by subclassing
 * a packaged class. So `finalByDefault` runs unqualified here, and there is no
 * `swappableModelsAreNotFinal` counter-weight to balance it against; that preset's whole
 * reason to exist (the 7×-shipped `final` on a swappable model) cannot arise in this package.
 */
ArchPresets::finalByDefault('RoundlyConsulting\QueryBuilder');

/**
 * Query-builder does no cryptography. The ban is a standing guard — this package parses
 * untrusted request input, so a hand-rolled hash for a filter token or a cursor is exactly
 * the kind of thing that would appear here rather than in crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\QueryBuilder');

/**
 * The Dependency Policy as a test. No `alsoAllow`: query-builder's `require` ships only
 * php/illuminate/symfony/roundly, and the workflow installs test tooling with `--dev`, so
 * nothing legitimately lands in `require` that this must forgive. If this goes red, the
 * graph is wrong — never widen the allow-list to quiet it.
 *
 * This is the machine-checked half of the bespoke `toOnlyUse` rule below: that one pins what
 * the source may *import*, this one pins what the package may *install*. They fail for
 * different reasons and neither implies the other.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();

/*
 * `ArchPresets::modelsResolveThroughSeam()` is deliberately NOT adopted: query-builder ships
 * no Eloquent model and no `*_model` config key, so both halves are structurally inert —
 * there is no swap literal to stray and no configured class to resolve. Pre-classified by
 * `Swap? = 0`, per the plan's settled rule; this is not a per-row judgement.
 */

// ---------------------------------------------------------------------------
// The package's own rules. The presets replace the generic ones, not these.
// ---------------------------------------------------------------------------

/**
 * Allow-listing the permitted vendor roots (Laravel/Symfony, our own Enums and toolkit
 * helpers, PHP built-ins) bans every other third-party vendor implicitly — none is named.
 *
 * Kept: this is a stricter, import-level statement of the Dependency Policy that no preset
 * expresses, and it is the rule that would catch a banned vendor reaching the source through
 * a transitive dependency the `require` block never names.
 */
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

arch('contracts are interfaces')
    ->expect('RoundlyConsulting\QueryBuilder\Contracts')
    ->toBeInterfaces();

arch('enums are enums')
    ->expect('RoundlyConsulting\QueryBuilder\Enums')
    ->toBeEnums();
