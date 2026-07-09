<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;
use RoundlyConsulting\Translatable\Support\Translations;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('en');
});

it('matches per-locale, case-insensitively', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing Basics', 'sk' => 'Investovanie']]);
    Topic::query()->create(['name' => ['en' => 'Cooking', 'sk' => 'Varenie']]);

    $results = Translations::whereLike(
        Topic::query(),
        new TranslationSearch(fields: ['name'], term: 'invest'),
    )->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->getTranslation('name', 'en'))->toBe('Investing Basics');
});

it('searches multiple fields', function (): void {
    Topic::query()->create(['name' => ['en' => 'A'], 'description' => ['en' => 'about investing']]);
    Topic::query()->create(['name' => ['en' => 'B'], 'description' => ['en' => 'about cooking']]);

    $results = Translations::whereLike(
        Topic::query(),
        new TranslationSearch(fields: ['name', 'description'], term: 'investing'),
    )->get();

    expect($results)->toHaveCount(1);
});
