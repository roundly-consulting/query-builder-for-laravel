<?php

declare(strict_types=1);

/**
 * The config-key contract, pinned in BOTH directions:
 *
 *   forward — every key the source reads must exist in the shipped config file (a key the
 *             code reads but the package never ships is unreachable). This is shops #18.
 *   reverse — every leaf the config file ships must be read by the source (a key the package
 *             ships but nothing reads is a documented feature that silently does nothing).
 *             **This is the direction that found query-builder's own bug #32.**
 *
 * This replaces ~120 lines of hand-rolled contract — a local scraper, a
 * RecursiveDirectoryIterator, a `Arr::dot()` walk and an interpolation guard — with the
 * shipped expectation. It is not a like-for-like rewrite; the preset is stronger on the two
 * points the local version had to get right by hand:
 *
 *  - it scrapes **source tokens**, so a docblock mentioning a key is a comment token and
 *    never a read (media #27's near-miss: a regex over raw text stayed green with the fix
 *    reverted);
 *  - it **flags** an interpolated key under the prefix rather than silently ignoring it, so
 *    the guard the local file wrote by hand is part of the assertion instead of a separate
 *    test that could be deleted without anything noticing.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/query-builder.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        // No `excludeFromReverse` for the provider — a deliberate departure from the local
        // version this replaces, which dropped the provider from the reverse direction on
        // the grounds that its `about` payload renders every key and "displaying a value is
        // not applying it".
        //
        // That reasoning is sound in the abstract and wrong for our provider shape: the
        // toolkit's PackageServiceProvider both renders (`contributesToAbout()`) and does
        // real reads (`bindFromConfig()`) from the same file, so excluding it discards the
        // only reader of every bound key and weakens the reverse direction rather than
        // sharpening it. The keys that were only ever *rendered* are covered because the
        // parameters, bounds and modes below are all read by the request/filter code too —
        // which is the point: if a key is genuinely read nowhere but `about`, that is bug
        // #32 again and this should say so.
        'extraReadPrefixes' => ['query-builder.'],
    ]);
});
