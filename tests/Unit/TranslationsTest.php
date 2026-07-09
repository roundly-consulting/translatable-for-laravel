<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\Support\Translations;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

it('reads the supported locales from the bound source', function (): void {
    expect(Translations::supported())->toBe(['en', 'sk']);
});

it('honours a rebound SupportedLocales everywhere', function (): void {
    app()->bind(SupportedLocales::class, fn () => new class implements SupportedLocales
    {
        public function supported(): array
        {
            return ['en', 'sk', 'de'];
        }
    });

    expect(Translations::supported())->toBe(['en', 'sk', 'de'])
        ->and(Translations::fromInput(['de' => 'Investieren']))->toBe(['de' => 'Investieren']);
});

it('normalises a bare string to the current locale', function (): void {
    app()->setLocale('sk');

    expect(Translations::fromInput('Investovanie'))->toBe(['sk' => 'Investovanie'])
        ->and(Translations::fromInput(''))->toBe([]);
});

it('drops blank and unsupported locales from input', function (): void {
    $map = Translations::fromInput([
        'en' => 'Investing',
        'sk' => '',
        'de' => 'Investieren',
        'fr' => null,
    ]);

    expect($map)->toBe(['en' => 'Investing']);
});

it('returns an empty map for non-array, non-string input', function (): void {
    expect(Translations::fromInput(42))->toBe([]);
});

it('builds required rules that reject an all-blank map', function (): void {
    $rules = Translations::rules('name', required: true);

    $validator = Validator::make(['name' => ['en' => '', 'sk' => '']], $rules);

    expect($validator->fails())->toBeTrue();
});

it('accepts a partial map under required rules', function (): void {
    $rules = Translations::rules('name', required: true);

    $validator = Validator::make(['name' => ['en' => 'Investing', 'sk' => '']], $rules);

    expect($validator->fails())->toBeFalse();
});

it('treats optional fields as sometimes-array', function (): void {
    $rules = Translations::rules('description', required: false);

    expect($rules['description'])->toBe(['sometimes', 'array']);
    expect(Validator::make([], $rules)->fails())->toBeFalse();
});

it('applies PATCH changes leaving untouched locales intact', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    Translations::apply($topic, TranslationChanges::make(['name' => ['sk' => 'Sporenie']]));

    expect($topic->getTranslations('name'))->toBe(['en' => 'Investing', 'sk' => 'Sporenie']);
});
