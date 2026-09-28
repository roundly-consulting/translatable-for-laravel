<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;
use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;
use RoundlyConsulting\Translatable\Exceptions\NotATranslatableAttributeException;
use RoundlyConsulting\Translatable\Facades\Translatable;
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

// F9 — LIKE wildcards in the search term are escaped (value stays bound).

it('escapes LIKE wildcards in the search term', function (): void {
    $query = Translatable::search(
        Topic::query(),
        new TranslationSearch(fields: ['name'], term: '50%_x'),
    );

    expect($query->getBindings())->toContain('%50\%\_x%');
});
