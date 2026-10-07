<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;
use RoundlyConsulting\Translatable\Facades\Translatable;
use RoundlyConsulting\Translatable\Tests\Fixtures\LooseFallbackTopic;
use RoundlyConsulting\Translatable\Tests\Fixtures\StrictTopic;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;
use RoundlyConsulting\Translatable\Tests\Fixtures\UninitialisedFallbackTopic;

/**
 * `FallbackMode::Any` picks "the first available value". PostgreSQL jsonb and MySQL JSON
 * re-order a stored object's keys (shortest first, then byte order), so "first" by map order
 * rendered one row in a different language before and after a reload, and per engine. The
 * walk follows the supported-locale order instead, then the remaining locales alphabetically.
 */
beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('fr');
    config()->set('translatable.fallback', FallbackMode::Any);
    config()->set('translatable.fallback_locale', 'en');
    config()->set('translatable.locales', ['en', 'sk']);
});

it('renders the same Any value before and after a reload', function (): void {
    $topic = Topic::query()->create(['name' => ['de' => 'Hallo', 'sk' => 'Ahoj']]);

    expect($topic->name)->toBe('Ahoj')
        ->and(Topic::query()->findOrFail($topic->id)->name)->toBe('Ahoj');
});

it('ignores the key order the engine stores', function (): void {
    $id = DB::table('topics')->insertGetId([
        'name' => '{"de": "Hallo", "sk": "Ahoj"}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Topic::query()->findOrFail($id)->name)->toBe('Ahoj');
});

it('follows the supported-locale order on the facade', function (): void {
    config()->set('translatable.locales', ['en', 'de', 'sk']);

    expect(Translatable::resolve(['sk' => 'Ahoj', 'de' => 'Hallo']))->toBe('Hallo');
});

it('resolves a raw map with nulls and numbers through the facade', function (): void {
    expect(Translatable::resolve(['en' => null, 'sk' => 'Ahoj'], 'de'))->toBe('Ahoj')
        ->and(Translatable::resolve(['sk' => 5], 'de'))->toBe('5');
});

/**
 * Per-model overrides. A string mode used to be ignored (so `'none'` silently widened to the
 * global `Any` — the cross-locale disclosure the config file warns about), an uninitialised
 * typed property threw `Error` on every read, and a malformed per-model locale was accepted.
 */
it('reads a per-model fallback mode declared as a string', function (): void {
    $topic = (new LooseFallbackTopic)->setTranslations('name', ['de' => 'Nur Deutsch']);
    app()->setLocale('sk');

    expect($topic->translationFallbackMode())->toBe(FallbackMode::None)
        ->and($topic->name)->toBeNull()
        ->and($topic->overrideFallback('fallback')->translationFallbackMode())->toBe(FallbackMode::Fallback)
        ->and($topic->overrideFallback(FallbackMode::None)->translationFallbackMode())->toBe(FallbackMode::None);
});

it('refuses a per-model fallback mode that names no mode', function (mixed $mode): void {
    $topic = (new LooseFallbackTopic)->overrideFallback($mode);

    expect(fn () => $topic->translationFallbackMode())
        ->toThrow(InvalidConfigurationException::class, LooseFallbackTopic::class.'::$translatableFallbackMode');
})->with(['a typo' => 'nope', 'an int' => 1, 'an upper-case value' => 'NONE']);

it('uses the config when a per-model override is null or never initialised', function (): void {
    config()->set('translatable.fallback', FallbackMode::Fallback);
    config()->set('translatable.fallback_locale', 'en');

    $uninitialised = (new UninitialisedFallbackTopic)->setTranslations('name', ['en' => 'Investing']);
    $null = (new LooseFallbackTopic)->overrideFallback(null);

    expect($uninitialised->translationFallbackMode())->toBe(FallbackMode::Fallback)
        ->and($uninitialised->translationFallbackLocale())->toBe('en')
        ->and($uninitialised->name)->toBe('Investing')
        ->and($null->translationFallbackMode())->toBe(FallbackMode::Fallback)
        ->and($null->translationFallbackLocale())->toBe('en');
});

it('validates a per-model fallback locale', function (): void {
    $topic = new LooseFallbackTopic;

    expect($topic->overrideFallback(null, 'sk')->translationFallbackLocale())->toBe('sk')
        // Blank means "no fallback locale", as it does in config.
        ->and($topic->overrideFallback(null, '')->translationFallbackLocale())->toBe('')
        ->and(fn () => $topic->overrideFallback(null, "en'")->translationFallbackLocale())
        ->toThrow(InvalidLocaleException::class)
        ->and(fn () => $topic->overrideFallback(null, "en\n")->translationFallbackLocale())
        ->toThrow(InvalidLocaleException::class)
        ->and(fn () => $topic->overrideFallback(null, 5)->translationFallbackLocale())
        ->toThrow(InvalidConfigurationException::class, LooseFallbackTopic::class.'::$translatableFallbackLocale');
});

it('keeps typed enum overrides working', function (): void {
    $topic = new StrictTopic;

    expect($topic->translationFallbackMode())->toBe(FallbackMode::None)
        ->and($topic->translationFallbackLocale())->toBe('sk');
});
