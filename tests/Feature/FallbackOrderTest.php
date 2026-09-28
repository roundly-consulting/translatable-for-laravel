<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Facades\Translatable;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

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
