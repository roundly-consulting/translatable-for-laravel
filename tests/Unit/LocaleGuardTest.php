<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;
use RoundlyConsulting\Translatable\Exceptions\TranslatableException;
use RoundlyConsulting\Translatable\Support\LocaleGuard;

it('accepts well-formed locale keys', function (string $locale): void {
    expect(LocaleGuard::isValid($locale))->toBeTrue()
        ->and(LocaleGuard::ensure($locale))->toBe($locale);
})->with(['en', 'sk', 'de', 'en_US', 'pt-BR', 'zh_Hans_CN']);

it('rejects malformed and hostile locale keys', function (string $locale): void {
    expect(LocaleGuard::isValid($locale))->toBeFalse()
        ->and(fn () => LocaleGuard::ensure($locale))->toThrow(InvalidLocaleException::class);
})->with([
    "en'); DROP TABLE topics;--",
    'en"',
    '<script>',
    'e n',
    'ENGLISH',
    'toolongprimary',
    '',
    '1en',
]);

it('enforces supported membership only in strict mode', function (): void {
    expect(LocaleGuard::ensure('de', strict: false, supported: ['en', 'sk']))->toBe('de');

    expect(fn () => LocaleGuard::ensure('de', strict: true, supported: ['en', 'sk']))
        ->toThrow(InvalidLocaleException::class);

    expect(LocaleGuard::ensure('en', strict: true, supported: ['en', 'sk']))->toBe('en');
});

it('validates SQL identifiers', function (): void {
    expect(LocaleGuard::isValidIdentifier('topics'))->toBeTrue()
        ->and(LocaleGuard::isValidIdentifier('slug_column_2'))->toBeTrue()
        ->and(LocaleGuard::isValidIdentifier('topics; DROP TABLE users'))->toBeFalse()
        ->and(LocaleGuard::isValidIdentifier('2col'))->toBeFalse()
        ->and(LocaleGuard::ensureIdentifier('topics', 'table name'))->toBe('topics');
});

it('throws a typed exception for a hostile identifier', function (): void {
    expect(fn () => LocaleGuard::ensureIdentifier('topics)); DROP TABLE topics;--', 'table name'))
        ->toThrow(TranslatableException::class);
});
