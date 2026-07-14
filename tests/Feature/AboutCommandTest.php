<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function aboutOutput(): string
{
    Artisan::call('about', ['--only' => 'translatable']);

    return Artisan::output();
}

it('contributes a translatable section to about', function (): void {
    $output = aboutOutput();

    expect($output)->toContain('Translatable')
        ->and($output)->toContain('Locales')
        ->and($output)->toContain('2 configured')
        ->and($output)->toContain('ConfigSupportedLocales')
        ->and($output)->toContain('any');
});

/**
 * A locale list is the host's market footprint and its reserved slugs name the routes it
 * protects (`super-admin-console`), so `about` reports counts and switches — never a value.
 * The positive assertion runs first so this can never pass because the section is empty:
 * `Artisan::call()` + `Artisan::output()` is used deliberately, because
 * `app(Kernel::class)->output()` returns `''` and would make the whole test vacuous.
 */
it('never renders a configured locale, reserved slug, or column name', function (): void {
    config()->set('translatable.locales', ['en', 'sk', 'zz-internal-market']);
    config()->set('translatable.fallback_locale', 'zz-internal-market');
    config()->set('translatable.slug', [
        'source_field' => 'internal_headline_column',
        'separator' => '~',
        'max_words' => 4,
        'reserved' => ['super-admin-console', 'billing-export'],
    ]);

    $output = aboutOutput();

    // Guard the guard: the section really did render, with the shape we expect.
    expect($output)->toContain('3 configured')
        ->and($output)->toContain('2 reserved')
        ->and($output)->toContain('CUSTOM')
        ->and($output)->toContain('SET');

    expect($output)
        ->not->toContain('zz-internal-market')
        ->not->toContain('internal_headline_column')
        ->not->toContain('super-admin-console')
        ->not->toContain('billing-export');
});

it('reports the slug bounds and the strict-locale switch', function (): void {
    config()->set('translatable.strict_locales', true);

    expect(aboutOutput())
        ->toContain('ON')
        ->toContain('12');
});
