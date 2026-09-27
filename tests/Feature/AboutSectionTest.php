<?php

declare(strict_types=1);

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`. Every "does not leak" check was vacuous — passing against empty output.
 *
 * `ServiceProviderTest`'s existing `about` test is a real capture rather than that vacuous
 * shape, and it is kept — but it is entirely positive, and nothing in this suite ever
 * asserted what must *never* render. That is what this adds, in order: output non-empty →
 * every `mustRender` string present → only then no secret renders.
 *
 * This package is an unusual case for A, and the reason is worth stating rather than
 * shrugging at: **query-builder's config IS its public wire contract**. The parameter names,
 * bounds and modes are things a client already sends over the network, so rendering them is
 * correct and the section is deliberately transparent. What must not appear is anything
 * about the *host's data*: this package is handed a database connection and untrusted filter
 * input, and an `about` section is not the place for either. The pin below is therefore
 * about the boundary of that transparency, not about credentials.
 */
it('renders the wire contract without leaking anything behind it', function (): void {
    // The host's own connection details, reachable from where the about closure runs. None
    // of this is query-builder's to print. A separate connection carries the canary: planting
    // it on the live `testing` connection breaks the next reconnect on a password-protected
    // real engine (CI's pgsql/mysql legs), which then fails for the wrong reason.
    config()->set('database.connections.host', [
        'driver' => 'pgsql',
        'host' => 'db.internal',
        'database' => 'archived_posts',
        'username' => 'app',
        'password' => 'pa55word-should-never-render',
    ]);
    config()->set('app.key', 'base64:c2VjcmV0LWFwcC1rZXktbmV2ZXItcmVuZGVy');

    expect('query-builder')->toLeakNoSecrets(
        secrets: [
            'pa55word-should-never-render',
            'base64:c2VjcmV0LWFwcC1rZXktbmV2ZXItcmVuZGVy',
            // The section reports the shape of the contract, never a table, column or model
            // from the host's schema.
            'archived_posts',
        ],
        mustRender: [
            'Filter parameter',
            'Sort parameter',
            'Page parameters',
            'Page size',
            'Unknown filter',
            'Unknown sort',
            'Request limits',
            // The values themselves must render — the positive proof that these lines report
            // rather than sitting silently empty, which is what would let the negative half
            // pass for the wrong reason.
            '20 default, 100 max',
            '50 value(s), 255 char(s), 5 sort(s)',
            'REJECT',
        ],
    );
});
