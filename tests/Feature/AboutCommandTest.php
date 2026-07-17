<?php

declare(strict_types=1);

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`, so every "does not leak" check was vacuous.
 *
 * The hand-rolled version this replaces was already on the right side of that — it captured
 * through `Artisan::call()` + `Artisan::output()` and said so, and it ran its positive
 * assertions first. The expectation's contribution is that the discipline stops being a
 * convention the next author has to notice: `mustRender` is a required, non-empty argument
 * that throws at call time and is asserted BEFORE any secret check runs.
 *
 * What translatable must never render is the host's commercial position: the locale list is
 * its market footprint (an unannounced market is a leak), the reserved slugs name the routes
 * it protects, and the slug source field names a column in the host's schema. All report as
 * counts and switches.
 */
it('contributes a translatable section to about', function (): void {
    expect('translatable')->toLeakNoSecrets(
        secrets: ['zz-internal-market'],
        mustRender: ['Locales', '2 configured', 'ConfigSupportedLocales', 'any'],
    );
});

it('never renders a configured locale, reserved slug, or column name', function (): void {
    config()->set('translatable.locales', ['en', 'sk', 'zz-internal-market']);
    config()->set('translatable.fallback_locale', 'zz-internal-market');
    config()->set('translatable.slug', [
        'source_field' => 'internal_headline_column',
        'separator' => '~',
        'max_words' => 4,
        'reserved' => ['super-admin-console', 'billing-export'],
    ]);

    expect('translatable')->toLeakNoSecrets(
        secrets: [
            // An unannounced market the host has not launched yet.
            'zz-internal-market',
            // The routes the host protects.
            'super-admin-console',
            'billing-export',
            // A column name from the host's own schema.
            'internal_headline_column',
            // The custom separator renders as CUSTOM, never as its value.
            '~',
        ],
        mustRender: [
            // Counts and switches — the positive proof the section rendered, which is what
            // makes every absence above mean something.
            '3 configured',
            '2 reserved',
            'CUSTOM',
            'SET',
        ],
    );
});
