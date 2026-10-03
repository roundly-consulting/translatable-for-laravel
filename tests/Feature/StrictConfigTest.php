<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Support\TranslationManager;

/**
 * A typo in the host's translatable config fails loudly. Before: every `locales` entry was
 * `(string)` cast (so `1` became a "locale"), and a non-string or malformed fallback locale
 * was cast and then silently never matched.
 */
it('refuses a locales value that is not a list of well-formed locales (strict config)', function (mixed $locales): void {
    config()->set('translatable.locales', $locales);

    expect(fn () => app(SupportedLocales::class)->supported())
        ->toThrow(InvalidConfigurationException::class, 'translatable.locales');
})->with([
    'a string' => 'en,sk',
    'an int entry' => [['en', 1]],
    'a blank entry' => [['en', '']],
    'a malformed entry' => [['en', 'English']],
]);

it('reads a valid locales list, an empty one and an unset one (strict config)', function (): void {
    config()->set('translatable.locales', ['en', 'pt-BR', 'zh_Hans_CN']);
    expect(app(SupportedLocales::class)->supported())->toBe(['en', 'pt-BR', 'zh_Hans_CN']);

    config()->set('translatable.locales', []);
    expect(app(SupportedLocales::class)->supported())->toBe([]);

    config()->set('translatable.locales', null);
    expect(app(SupportedLocales::class)->supported())->toBe([]);
});

it('refuses a non-string or malformed fallback locale (strict config)', function (mixed $locale): void {
    config()->set('translatable.fallback_locale', $locale);

    expect(fn () => app(TranslationManager::class)->fallbackLocale())
        ->toThrow(InvalidConfigurationException::class, 'translatable.fallback_locale');
})->with(['an array' => [['en']], 'an int' => 1, 'a typo' => 'English', 'injection' => "en'; --"]);

it('keeps blank and unset as the documented "no fallback locale" (strict config)', function (mixed $locale): void {
    config()->set('translatable.fallback_locale', $locale);

    expect(app(TranslationManager::class)->fallbackLocale())->toBe('');
})->with(['unset' => null, 'blank' => '']);
