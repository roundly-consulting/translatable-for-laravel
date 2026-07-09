<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\DataTransferObjects\SlugOptions;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;
use RoundlyConsulting\Translatable\DataTransferObjects\UniqueSlugContext;

it('builds TranslationChanges', function (): void {
    $changes = TranslationChanges::make(['name' => ['en' => 'Hi']]);

    expect($changes->fields)->toBe(['name' => ['en' => 'Hi']]);
});

it('builds TranslationSearch', function (): void {
    $search = new TranslationSearch(fields: ['name', 'description'], term: 'invest');

    expect($search->fields)->toBe(['name', 'description'])
        ->and($search->term)->toBe('invest');
});

it('builds UniqueSlugContext with defaults', function (): void {
    $context = new UniqueSlugContext(input: 'x', table: 'topics');

    expect($context->column)->toBe('slug')
        ->and($context->ignoreId)->toBeNull()
        ->and($context->table)->toBe('topics');
});

it('builds SlugOptions from config', function (): void {
    config()->set('translatable.slug', [
        'source_field' => 'title',
        'separator' => '_',
        'max_words' => 4,
        'reserved' => ['edit', 'create'],
    ]);

    $options = SlugOptions::fromConfig();

    expect($options->sourceField)->toBe('title')
        ->and($options->separator)->toBe('_')
        ->and($options->maxWords)->toBe(4)
        ->and($options->reserved)->toBe(['edit', 'create']);
});

it('falls back to defaults when config is absent', function (): void {
    config()->set('translatable.slug', []);

    $options = SlugOptions::fromConfig();

    expect($options->sourceField)->toBe('name')
        ->and($options->separator)->toBe('-')
        ->and($options->maxWords)->toBe(12)
        ->and($options->reserved)->toBe([]);
});
