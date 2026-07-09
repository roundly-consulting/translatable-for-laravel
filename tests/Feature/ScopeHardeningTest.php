<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;
use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;
use RoundlyConsulting\Translatable\Exceptions\NotATranslatableAttributeException;
use RoundlyConsulting\Translatable\Exceptions\TranslatableException;
use RoundlyConsulting\Translatable\Support\TranslatableSlug;
use RoundlyConsulting\Translatable\Support\Translations;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    app()->setLocale('en');
});

// F3 — scopes guard the field and the locale before the JSON-path expression.

it('rejects a hostile locale in whereLocale', function (): void {
    expect(fn () => Topic::query()->whereLocale('name', 'x', "en' OR 1=1 --"))
        ->toThrow(InvalidLocaleException::class);
});

it('rejects a non-translatable field in whereLocale', function (): void {
    expect(fn () => Topic::query()->whereLocale('secret', 'x', 'en'))
        ->toThrow(NotATranslatableAttributeException::class);
});

it('guards whereHasLocale and whereMissingLocale', function (): void {
    expect(fn () => Topic::query()->whereHasLocale('name', "en'--"))
        ->toThrow(InvalidLocaleException::class);

    expect(fn () => Topic::query()->whereMissingLocale('secret'))
        ->toThrow(NotATranslatableAttributeException::class);
});

it('rejects a hostile locale in whereLocaleSlug', function (): void {
    expect(fn () => Topic::query()->whereLocaleSlug('investing', "en'; DROP TABLE topics;--"))
        ->toThrow(InvalidLocaleException::class);
});

it('guards slugTaken against a hostile column and locale', function (): void {
    expect(fn () => TranslatableSlug::slugTaken('topics', 'slug); DROP TABLE topics;--', 'en', 'x', null))
        ->toThrow(TranslatableException::class);

    expect(fn () => TranslatableSlug::slugTaken('topics', 'slug', "en'--", 'x', null))
        ->toThrow(InvalidLocaleException::class);
});

// F1 — the index DDL helper and command reject hostile identifiers.

it('rejects a hostile table name in uniqueIndexes', function (): void {
    expect(fn () => TranslatableSlug::uniqueIndexes('topics); DROP TABLE topics;--'))
        ->toThrow(TranslatableException::class);
});

it('rejects a hostile column name in uniqueIndexes', function (): void {
    expect(fn () => TranslatableSlug::uniqueIndexes('topics', "slug'"))
        ->toThrow(TranslatableException::class);
});

it('fails the slug-indexes command on a hostile table argument', function (): void {
    $this->artisan('translatable:slug-indexes', ['table' => 'topics; DROP TABLE users'])
        ->expectsOutputToContain('Invalid table name')
        ->assertFailed();
});

// F9 — LIKE wildcards in the search term are escaped (value stays bound).

it('escapes LIKE wildcards in the search term', function (): void {
    $query = Translations::whereLike(
        Topic::query(),
        new TranslationSearch(fields: ['name'], term: '50%_x'),
    );

    expect($query->getBindings())->toContain('%50\%\_x%');
});
