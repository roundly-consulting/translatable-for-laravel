<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;
use RoundlyConsulting\Translatable\Exceptions\InvalidTranslationValueException;
use RoundlyConsulting\Translatable\Exceptions\NotATranslatableAttributeException;
use RoundlyConsulting\Translatable\Exceptions\TranslatableException;
use RoundlyConsulting\Translatable\Facades\Translatable;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    app()->setLocale('en');
    config()->set('translatable.strict_locales', false);
});

// F2 — locale key validation on every write path.

it('rejects a malformed locale key on a mass-assigned array write', function (): void {
    expect(fn () => new Topic(['name' => ['<script>alert(1)</script>' => 'x']]))
        ->toThrow(InvalidLocaleException::class);
});

it('rejects a SQL-injection locale key on setTranslations', function (): void {
    $topic = new Topic;

    expect(fn () => $topic->setTranslations('name', ["en'); DROP TABLE topics;--" => 'x']))
        ->toThrow(InvalidLocaleException::class);
});

it('rejects a malformed locale key on setTranslation', function (): void {
    $topic = new Topic;

    expect(fn () => $topic->setTranslation('name', 'bad locale!', 'x'))
        ->toThrow(InvalidLocaleException::class);
});

it('does not grow the map with thousands of junk locale keys', function (): void {
    $junk = [];
    for ($i = 0; $i < 2000; $i++) {
        $junk["junk!{$i}"] = 'x';
    }

    expect(fn () => new Topic(['name' => $junk]))
        ->toThrow(InvalidLocaleException::class);
});

it('accepts any well-formed locale by default (non-strict)', function (): void {
    $topic = new Topic(['name' => ['de' => 'Investieren']]);

    expect($topic->getTranslations('name'))->toBe(['de' => 'Investieren']);
});

it('rejects a well-formed but unsupported locale in strict mode', function (): void {
    config()->set('translatable.strict_locales', true);

    expect(fn () => new Topic(['name' => ['de' => 'Investieren']]))
        ->toThrow(InvalidLocaleException::class);

    // A supported locale still writes fine under strict mode.
    expect((new Topic(['name' => ['en' => 'Investing']]))->getTranslations('name'))
        ->toBe(['en' => 'Investing']);
});

it('keeps the safe fromInput ingestion path working', function (): void {
    // Junk keys are dropped BEFORE they reach the model, so the write path never sees them.
    $map = Translatable::fromInput(['en' => 'Investing', '<script>' => 'x', 'sk' => 'Investovanie']);
    $topic = new Topic(['name' => $map]);

    expect($topic->getTranslations('name'))->toBe(['en' => 'Investing', 'sk' => 'Investovanie']);
});

// F4 — json_encode failure surfaces loudly.

it('throws instead of silently corrupting on invalid UTF-8', function (): void {
    $topic = new Topic;

    expect(fn () => $topic->setTranslations('name', ['en' => "\xB1\x31\xB2"]))
        ->toThrow(TranslatableException::class);
});

// F5 — non-scalar / type-juggling values.

it('rejects a nested-array payload', function (): void {
    $topic = new Topic;

    expect(fn () => $topic->setTranslations('name', ['en' => ['nested' => 'x']]))
        ->toThrow(InvalidTranslationValueException::class);
});

it('rejects a boolean value instead of juggling it to "1"', function (): void {
    $topic = new Topic;

    expect(fn () => $topic->name = ['en' => true])
        ->toThrow(InvalidTranslationValueException::class);
});

it('casts int and float values explicitly', function (): void {
    $topic = new Topic(['name' => ['en' => 42, 'sk' => 1.5]]);

    expect($topic->getTranslations('name'))->toBe(['en' => '42', 'sk' => '1.5']);
});

it('skips non-scalar stored values on read without warnings', function (): void {
    $this->createTopicsTable();

    DB::table('topics')->insert([
        'id' => 1,
        'name' => json_encode(['en' => ['nested', 'array'], 'sk' => 'Investovanie']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $topic = Topic::query()->find(1);

    expect($topic->getTranslations('name'))->toBe(['sk' => 'Investovanie']);
});

// F11 — guardTranslatable on the read helpers.

it('guards hasTranslation against a non-translatable attribute', function (): void {
    $topic = new Topic;

    expect(fn () => $topic->hasTranslation('password', 'en'))
        ->toThrow(NotATranslatableAttributeException::class);
});

it('guards getTranslatedLocales against a non-translatable attribute', function (): void {
    $topic = new Topic;

    expect(fn () => $topic->getTranslatedLocales('password'))
        ->toThrow(NotATranslatableAttributeException::class);
});
