<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Facades\Translatable;
use RoundlyConsulting\Translatable\Support\TranslationManager;
use RoundlyConsulting\Translatable\Support\Translations;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    app()->setLocale('en');
    config()->set('translatable.fallback', FallbackMode::Any);
    config()->set('translatable.fallback_locale', 'en');
});

it('resolves the bound manager through the facade', function (): void {
    expect(Translatable::getFacadeRoot())->toBeInstanceOf(TranslationManager::class)
        ->and(app(TranslationManager::class))->toBe(app(TranslationManager::class)); // singleton
});

it('mirrors every Translations helper through the facade', function (): void {
    app()->setLocale('sk');

    expect(Translatable::supported())->toBe(Translations::supported())
        ->and(Translatable::currentLocale())->toBe('sk')
        ->and(Translatable::fromInput('Investovanie'))->toBe(['sk' => 'Investovanie'])
        ->and(Translatable::fallbackMode())->toBe(FallbackMode::Any)
        ->and(Translatable::fallbackLocale())->toBe('en');
});

it('reads the configured fallback mode and locale', function (): void {
    config()->set('translatable.fallback', 'fallback');
    config()->set('translatable.fallback_locale', 'sk');

    expect(Translatable::fallbackMode())->toBe(FallbackMode::Fallback)
        ->and(Translatable::fallbackLocale())->toBe('sk');

    config()->set('translatable.fallback', 'bogus');
    expect(Translatable::fallbackMode())->toBe(FallbackMode::Any);
});

it('coerces a FallbackMode instance straight from config', function (): void {
    config()->set('translatable.fallback', FallbackMode::None);

    expect(Translatable::fallbackMode())->toBe(FallbackMode::None);
});

it('honours a swapped manager everywhere the toolkit is used', function (): void {
    Translatable::swap(new class extends TranslationManager
    {
        public function supported(): array
        {
            return ['en', 'sk', 'de'];
        }
    });

    $topic = new Topic(['name' => ['en' => 'Investing']]);

    expect(Translatable::supported())->toBe(['en', 'sk', 'de'])
        ->and(Translations::supported())->toBe(['en', 'sk', 'de'])
        ->and(Translatable::fromInput(['de' => 'Investieren']))->toBe(['de' => 'Investieren'])
        ->and($topic->missingLocales('name'))->toBe(['sk', 'de']);
});

it('applies PATCH changes through the facade', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    Translatable::apply($topic, TranslationChanges::make(['name' => ['sk' => 'Sporenie']]));

    expect($topic->getTranslations('name'))->toBe(['en' => 'Investing', 'sk' => 'Sporenie']);
});

it('searches through the facade', function (): void {
    $this->createTopicsTable();
    Topic::query()->create(['name' => ['en' => 'Investing Basics']]);
    Topic::query()->create(['name' => ['en' => 'Cooking']]);

    $results = Translatable::search(
        Topic::query(),
        new TranslationSearch(fields: ['name'], term: 'invest'),
    );

    expect($results)->toBeInstanceOf(Builder::class)
        ->and($results->get())->toHaveCount(1);
});

it('runs a callback in a scoped locale and restores it', function (): void {
    app()->setLocale('en');
    $topic = new Topic(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    $value = Translatable::usingLocale('sk', fn (): string => $topic->name);

    expect($value)->toBe('Investovanie')
        ->and(app()->getLocale())->toBe('en');
});

it('restores the locale even when the callback throws', function (): void {
    app()->setLocale('en');

    expect(fn () => Translatable::usingLocale('sk', function (): void {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class);

    expect(app()->getLocale())->toBe('en');
});
