<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Tests\Fixtures\MarketSupportedLocales;

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
 * its market footprint (an unannounced market is a leak), and the fallback locale can name a
 * market too. Both report as a count and a switch. (Reserved slugs and the slug source column
 * moved to sluggable-for-laravel, whose own `about` section guards them.)
 */
it('contributes a translatable section to about', function (): void {
    expect('translatable')->toLeakNoSecrets(
        secrets: ['zz-internal-market'],
        mustRender: ['Locales', '2 configured', 'ConfigSupportedLocales', 'any'],
    );
});

it('never renders a configured locale', function (): void {
    config()->set('translatable.locales', ['en', 'sk', 'zz-internal-market']);
    config()->set('translatable.fallback_locale', 'zz-internal-market');
    config()->set('translatable.strict_locales', true);

    expect('translatable')->toLeakNoSecrets(
        // An unannounced market the host has not launched yet — in the list and as fallback.
        secrets: ['zz-internal-market'],
        mustRender: [
            // Counts and switches — the positive proof the section rendered, which is what
            // makes the absence above mean something.
            '3 configured',
            'SET',
            'ON',
        ],
    );
});

/**
 * The count comes from the bound `SupportedLocales` — the source the section names next to it
 * and the one the config file tells hosts to rebind — not from `translatable.locales`.
 */
it('counts the bound SupportedLocales, not the config list', function (): void {
    config()->set('translatable.locales', ['en', 'sk']);
    app()->bind(SupportedLocales::class, MarketSupportedLocales::class);

    expect('translatable')->toLeakNoSecrets(
        secrets: ['zz-internal-market'],
        mustRender: ['3 configured', 'MarketSupportedLocales'],
    );
});

/**
 * The "Fallback locale" row is a switch, so it must be able to show every position: the
 * shipped default follows `app.fallback_locale` (DEFAULT), an override is SET, and an unset
 * or blank value means no fallback locale at all (NONE). It used to read SET unconditionally,
 * because the shipped default resolves to a string and only `null` read as DEFAULT.
 */
it('reports whether the fallback locale is the app default, overridden or unset', function (mixed $configured, string $expected): void {
    config()->set('app.fallback_locale', 'en');
    config()->set('translatable.fallback_locale', $configured);

    Artisan::call('about', ['--only' => 'translatable']);
    $row = collect(explode("\n", Artisan::output()))
        ->first(static fn (string $line): bool => str_contains($line, 'Fallback locale'));

    expect(trim((string) $row))->toEndWith(' '.$expected);
})->with([
    'the shipped default' => ['en', 'DEFAULT'],
    'an override' => ['sk', 'SET'],
    'unset' => [null, 'NONE'],
    'blank' => ['', 'NONE'],
    'whitespace' => ['  ', 'NONE'],
]);
