<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;
use RoundlyConsulting\Translatable\Facades\Translatable;
use RoundlyConsulting\Translatable\Support\TranslationManager;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    app()->setLocale('en');
    config()->set('translatable.fallback', FallbackMode::Any);
    config()->set('translatable.fallback_locale', 'en');
});

/*
 * The facade contract. `toReachEveryAction()` is omitted: the package has no `src/Actions` —
 * it is pure locale-map computation, which the convention serves with a plain manager.
 * `toBeFakeable()` is omitted too: nothing here writes, queues, mails, dispatches or calls
 * out, so there is nothing for a fake to record (see the facade docblock).
 */
it('documents its root', function (): void {
    expect(Translatable::class)->toDocumentItsRoot();
});

it('resolves the bound manager through the facade', function (): void {
    expect(Translatable::getFacadeRoot())->toBeInstanceOf(TranslationManager::class)
        ->and(app(TranslationManager::class))->toBe(app(TranslationManager::class)); // singleton
});

it('serves the same API to an injected manager', function (): void {
    $manager = app(TranslationManager::class);
    app()->setLocale('sk');

    expect($manager)->toBe(Translatable::getFacadeRoot())
        ->and($manager->isSupported('sk'))->toBeTrue()
        ->and($manager->ensureLocale('sk'))->toBe('sk')
        ->and($manager->resolve(['en' => 'Investing']))->toBe('Investing')
        ->and($manager->fromInput('Investovanie'))->toBe(['sk' => 'Investovanie']);
});

it('exposes the locale helpers through the facade', function (): void {
    app()->setLocale('sk');

    expect(Translatable::supported())->toBe(['en', 'sk'])
        ->and(Translatable::currentLocale())->toBe('sk')
        ->and(Translatable::fromInput('Investovanie'))->toBe(['sk' => 'Investovanie'])
        ->and(Translatable::fallbackMode())->toBe(FallbackMode::Any)
        ->and(Translatable::fallbackLocale())->toBe('en');
});

it('drops a bare string when the current locale is not supported', function (): void {
    app()->setLocale('de');

    expect(Translatable::fromInput('Hallo'))->toBe([]);
});

it('never hands the write path a bare-string locale strict mode rejects', function (): void {
    config()->set('translatable.strict_locales', true);
    app()->setLocale('de');

    $topic = (new Topic)->setTranslations('name', Translatable::fromInput('Hallo'));

    expect($topic->getTranslations('name'))->toBe([]);
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
        ->and(Translatable::isSupported('de'))->toBeTrue()
        ->and(Translatable::fromInput(['de' => 'Investieren']))->toBe(['de' => 'Investieren'])
        ->and($topic->missingLocales('name'))->toBe(['sk', 'de'])
        ->and($topic->translationCompleteness())->toBe(1 / 9); // 3 fields x 3 swapped locales
});

it('routes model reads and writes through a swapped manager', function (): void {
    Translatable::swap(new class extends TranslationManager
    {
        public function supported(): array
        {
            return ['en', 'de'];
        }

        public function fallbackMode(): FallbackMode
        {
            return FallbackMode::Fallback;
        }

        public function fallbackLocale(): string
        {
            return 'de';
        }
    });
    config()->set('translatable.strict_locales', true);

    $topic = new Topic(['name' => ['en' => 'Investing', 'de' => 'Investieren']]);

    // Fallback settings: 'sk' is empty, so the swapped fallback locale 'de' answers.
    expect($topic->getTranslation('name', 'sk'))->toBe('Investieren')
        ->and($topic->translationFallbackMode())->toBe(FallbackMode::Fallback)
        ->and($topic->translationFallbackLocale())->toBe('de');

    // Strict locales: the swapped supported set is what the write path enforces.
    expect(fn () => $topic->setTranslation('name', 'sk', 'Investovanie'))
        ->toThrow(InvalidLocaleException::class);
});

it('checks whether a locale is supported', function (): void {
    expect(Translatable::isSupported('en'))->toBeTrue()
        ->and(Translatable::isSupported('sk'))->toBeTrue()
        ->and(Translatable::isSupported('de'))->toBeFalse()
        ->and(Translatable::isSupported('EN'))->toBeFalse()
        ->and(Translatable::isSupported(''))->toBeFalse();
});

it('ensures a locale is well-formed', function (): void {
    expect(Translatable::ensureLocale('en_US'))->toBe('en_US')
        ->and(Translatable::ensureLocale('de'))->toBe('de') // well-formed, unsupported: fine when lax
        ->and(fn () => Translatable::ensureLocale("en'); DROP TABLE topics;--"))
        ->toThrow(InvalidLocaleException::class, 'is not a valid locale key');
});

it('ensures a locale is supported in strict mode', function (): void {
    expect(Translatable::ensureLocale('sk', strict: true))->toBe('sk')
        ->and(fn () => Translatable::ensureLocale('de', strict: true))
        ->toThrow(InvalidLocaleException::class, 'not in the supported locales list')
        ->and(fn () => Translatable::ensureLocale('<script>', strict: true))
        ->toThrow(InvalidLocaleException::class, 'is not a valid locale key');
});

it('points the invalid-locale message at the facade', function (): void {
    expect(fn () => Translatable::ensureLocale('e n'))
        ->toThrow(InvalidLocaleException::class, 'Translatable::fromInput()');
});

it('resolves a raw map in the current locale with the configured fallback', function (): void {
    $map = ['en' => 'Investing', 'sk' => 'Investovanie'];

    app()->setLocale('sk');
    expect(Translatable::resolve($map))->toBe('Investovanie');

    app()->setLocale('de');
    expect(Translatable::resolve($map))->toBe('Investing'); // configured fallback locale 'en'

    config()->set('translatable.fallback', FallbackMode::None);
    expect(Translatable::resolve($map))->toBeNull();
});

it('resolves a raw map with an explicit locale, mode and fallback locale', function (): void {
    $map = ['sk' => 'Investovanie', 'de' => 'Investieren'];

    expect(Translatable::resolve($map, 'sk'))->toBe('Investovanie')
        ->and(Translatable::resolve($map, 'fr', FallbackMode::None))->toBeNull()
        ->and(Translatable::resolve($map, 'fr', FallbackMode::Fallback))->toBeNull() // 'en' is empty
        ->and(Translatable::resolve($map, 'fr', FallbackMode::Fallback, 'de'))->toBe('Investieren')
        ->and(Translatable::resolve($map, 'fr', FallbackMode::Any))->toBe('Investovanie')
        ->and(Translatable::resolve(['en' => '', 'sk' => 'Investovanie'], 'en', FallbackMode::Fallback))->toBeNull()
        ->and(Translatable::resolve([], 'en'))->toBeNull();
});

it('exposes the filled rule for a custom rule list', function (): void {
    $rules = ['name' => ['required', 'array', ...Translatable::filledRule('name')]];

    expect(Translatable::filledRule('name'))->toHaveCount(1)
        ->and(Validator::make(['name' => ['en' => '', 'sk' => '']], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['name' => ['en' => 'Investing']], $rules)->fails())->toBeFalse();

    $messages = Validator::make(['name' => ['en' => '']], $rules)->errors()->get('name');

    expect($messages)->toBe(['At least one locale must be provided.']);
});

it('fails the filled rule for a non-array value', function (): void {
    $rules = ['name' => Translatable::filledRule('name')];

    expect(Validator::make(['name' => 'Investing'], $rules)->fails())->toBeTrue();
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

it('keeps the locale-key format floor when a swapped manager waves every locale through', function (): void {
    Translatable::swap(new class extends TranslationManager
    {
        public function ensureLocale(string $locale, bool $strict = false): string
        {
            return $locale;
        }
    });

    $topic = new Topic;

    expect(fn () => $topic->setTranslation('name', "en'); DROP TABLE topics;--", 'x'))
        ->toThrow(InvalidLocaleException::class)
        ->and(fn () => Topic::query()->whereHasLocale('name', "en'))--"))
        ->toThrow(InvalidLocaleException::class);
});
