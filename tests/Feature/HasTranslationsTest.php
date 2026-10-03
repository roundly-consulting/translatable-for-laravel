<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Exceptions\NotATranslatableAttributeException;
use RoundlyConsulting\Translatable\Tests\Fixtures\EmptyStatusTopic;
use RoundlyConsulting\Translatable\Tests\Fixtures\ExcludingTopic;
use RoundlyConsulting\Translatable\Tests\Fixtures\StrictTopic;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('en');
    config()->set('translatable.fallback', FallbackMode::Any);
    config()->set('translatable.fallback_locale', 'en');
});

it('roundtrips a locale map through the database', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    $fresh = Topic::query()->find($topic->id);

    expect($fresh->getTranslations('name'))->toBe(['en' => 'Investing', 'sk' => 'Investovanie']);
});

it('reads the accessor through the fallback chain', function (): void {
    $topic = new Topic(['name' => ['sk' => 'Investovanie']]);
    app()->setLocale('de');

    expect($topic->name)->toBe('Investovanie');
});

it('returns the exact locale via getTranslation', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    expect($topic->getTranslation('name', 'sk'))->toBe('Investovanie')
        ->and($topic->getTranslation('name', 'de', false))->toBeNull();
});

it('sets the app locale value on a scalar write', function (): void {
    app()->setLocale('sk');
    $topic = new Topic;
    $topic->name = 'Investovanie';

    expect($topic->getTranslations('name'))->toBe(['sk' => 'Investovanie']);
});

it('replaces the whole map on an array write', function (): void {
    $topic = new Topic(['name' => ['en' => 'Old']]);
    $topic->name = ['en' => 'Investing', 'sk' => 'Investovanie'];

    expect($topic->getTranslations('name'))->toBe(['en' => 'Investing', 'sk' => 'Investovanie']);
});

it('forgets a locale when set to null or blank', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    $topic->setTranslation('name', 'sk', null);
    expect($topic->hasTranslation('name', 'sk'))->toBeFalse();

    $topic->setTranslation('name', 'en', '');
    expect($topic->getTranslations('name'))->toBe([]);
});

it('never stores blank values from an array write', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing', 'sk' => '']]);

    expect($topic->getTranslations('name'))->toBe(['en' => 'Investing']);
});

it('sets and replaces full maps', function (): void {
    $topic = new Topic;
    $topic->setTranslations('name', ['en' => 'Investing']);
    $topic->replaceTranslations(['description' => ['en' => 'A guide']]);

    expect($topic->getTranslations('name'))->toBe(['en' => 'Investing'])
        ->and($topic->getTranslations('description'))->toBe(['en' => 'A guide']);
});

it('returns every translatable map when no key is given', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing']]);

    expect($topic->getTranslations())->toBe([
        'name' => ['en' => 'Investing'],
        'description' => [],
        'slug' => [],
    ]);
});

it('forgets all translations for a field', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    $topic->forgetAllTranslations('name');

    expect($topic->getTranslations('name'))->toBe([]);
});

it('reports translated and missing locales', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing']]);

    expect($topic->getTranslatedLocales('name'))->toBe(['en'])
        ->and($topic->missingLocales('name'))->toBe(['sk']);
});

it('reports missing translations across every field', function (): void {
    $topic = new Topic([
        'name' => ['en' => 'Investing', 'sk' => 'Investovanie'],
        'description' => ['en' => 'A guide'],
        'slug' => ['en' => 'investing', 'sk' => 'investovanie'],
    ]);

    expect($topic->missingTranslations())->toBe([
        'name' => [],
        'description' => ['sk'],
        'slug' => [],
    ]);
});

it('reports full and partial translation for the whole model', function (): void {
    $full = new Topic([
        'name' => ['en' => 'A', 'sk' => 'A'],
        'description' => ['en' => 'B', 'sk' => 'B'],
        'slug' => ['en' => 'a', 'sk' => 'a'],
    ]);
    $partial = new Topic(['name' => ['en' => 'A']]);

    expect($full->isFullyTranslated())->toBeTrue()
        ->and($partial->isFullyTranslated())->toBeFalse()
        ->and($partial->isFullyTranslated('name'))->toBeFalse();

    $partial->setTranslation('name', 'sk', 'B');
    expect($partial->isFullyTranslated('name'))->toBeTrue();
});

it('computes translation completeness as a ratio', function (): void {
    $empty = new Topic;
    $full = new Topic([
        'name' => ['en' => 'A', 'sk' => 'A'],
        'description' => ['en' => 'B', 'sk' => 'B'],
        'slug' => ['en' => 'a', 'sk' => 'a'],
    ]);
    // 3 fields x 2 locales = 6 slots; en filled on all three = 3/6.
    $half = new Topic([
        'name' => ['en' => 'A'],
        'description' => ['en' => 'B'],
        'slug' => ['en' => 'a'],
    ]);

    expect($empty->translationCompleteness())->toBe(0.0)
        ->and($full->translationCompleteness())->toBe(1.0)
        ->and($half->translationCompleteness())->toBe(0.5);
});

it('ignores unsupported locale values in completeness', function (): void {
    $topic = new Topic(['name' => ['en' => 'A', 'de' => 'A'], 'description' => ['en' => 'B'], 'slug' => ['en' => 'a']]);

    // 'de' is unsupported, so it does not count toward filled slots: 3/6.
    expect($topic->translationCompleteness())->toBe(0.5);
});

it('honours the status excludes list', function (): void {
    $topic = new ExcludingTopic([
        'name' => ['en' => 'A', 'sk' => 'A'],
        'description' => ['en' => 'B', 'sk' => 'B'],
        // slug intentionally left empty — excluded from status.
    ]);

    expect($topic->missingTranslations())->toBe(['name' => [], 'description' => []])
        ->and($topic->isFullyTranslated())->toBeTrue()
        ->and($topic->translationCompleteness())->toBe(1.0);
});

it('treats a model with no status attributes as vacuously complete', function (): void {
    $topic = new EmptyStatusTopic;

    expect($topic->missingTranslations())->toBe([])
        ->and($topic->isFullyTranslated())->toBeTrue()
        ->and($topic->translationCompleteness())->toBe(1.0);
});

it('reports the translatable attribute surface', function (): void {
    $topic = new Topic;

    expect($topic->getTranslatableAttributes())->toBe(['name', 'description', 'slug'])
        ->and($topic->isTranslatableAttribute('name'))->toBeTrue()
        ->and($topic->isTranslatableAttribute('id'))->toBeFalse();
});

it('returns null from translatedOrNull without fallback', function (): void {
    $topic = new Topic(['name' => ['sk' => 'Investovanie']]);
    app()->setLocale('en');

    expect($topic->translatedOrNull('name'))->toBeNull();
});

it('honours a per-model fallback mode override', function (): void {
    $topic = new StrictTopic(['name' => ['sk' => 'Investovanie']]);
    app()->setLocale('en');

    // Config default is Any, but the model forces None → exact-or-null.
    expect($topic->getTranslation('name', 'en'))->toBeNull()
        ->and($topic->translationFallbackLocale())->toBe('sk')
        ->and($topic->translationFallbackMode())->toBe(FallbackMode::None);
});

it('coerces a string fallback mode from config', function (): void {
    config()->set('translatable.fallback', 'fallback');
    $topic = new Topic;

    expect($topic->translationFallbackMode())->toBe(FallbackMode::Fallback);

    config()->set('translatable.fallback', 'bogus');
    expect(fn () => $topic->translationFallbackMode())->toThrow(InvalidConfigurationException::class, 'translatable.fallback');
});

it('throws for a non-translatable attribute', function (): void {
    $topic = new Topic;

    expect(fn () => $topic->getTranslation('id'))
        ->toThrow(NotATranslatableAttributeException::class);
});

it('forgets a single locale via forgetTranslation', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    $topic->forgetTranslation('name', 'sk');

    expect($topic->getTranslations('name'))->toBe(['en' => 'Investing']);
});

it('treats a non-object JSON value as an empty map', function (): void {
    DB::table('topics')->insert([
        'id' => 1,
        'name' => json_encode('just a string'),
        'description' => json_encode(['en' => 'Guide', 'sk' => '']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $topic = Topic::query()->find(1);

    expect($topic->getTranslations('name'))->toBe([])
        ->and($topic->getTranslations('description'))->toBe(['en' => 'Guide']);
});

it('reads plain JSON literals stored by the hand-rolled convention', function (): void {
    DB::table('topics')->insert([
        'id' => 1,
        'name' => json_encode(['en' => 'Investing', 'sk' => 'Investovanie']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $topic = Topic::query()->find(1);

    expect($topic->getTranslations('name'))->toBe(['en' => 'Investing', 'sk' => 'Investovanie'])
        ->and($topic->name)->toBe('Investing');
});
