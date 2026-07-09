<?php

declare(strict_types=1);

use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Tests\Fixtures\StrictTopic;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('en');
    config()->set('translatable.fallback', FallbackMode::Any);
    config()->set('translatable.fallback_locale', 'en');
});

it('serializes translatable attributes as the localized value, not raw JSON', function (): void {
    $topic = Topic::query()->create([
        'name' => ['en' => 'Investing', 'sk' => 'Investovanie'],
        'description' => ['en' => 'A guide'],
    ]);

    $array = $topic->toArray();

    expect($array['name'])->toBe('Investing')
        ->and($array['description'])->toBe('A guide')
        ->and($array['name'])->toBe($topic->name);
});

it('round-trips toJson to the same localized value', function (): void {
    app()->setLocale('sk');
    $topic = new Topic(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($topic->toJson(), true);

    expect($decoded['name'])->toBe('Investovanie');
});

it('respects the effective fallback mode when serializing', function (): void {
    // StrictTopic forces FallbackMode::None, so an untranslated locale serializes as null.
    $topic = new StrictTopic(['name' => ['sk' => 'Investovanie']]);
    app()->setLocale('en');

    expect($topic->toArray()['name'])->toBeNull();
});

it('leaves non-translatable attributes untouched', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Investing']]);

    expect($topic->toArray())->toHaveKey('id')
        ->and($topic->toArray()['id'])->toBe($topic->id);
});

it('omits hidden translatable attributes from the array', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing'], 'description' => ['en' => 'Secret']]);
    $topic->setHidden(['description']);

    expect($topic->toArray())->not->toHaveKey('description')
        ->and($topic->toArray()['name'])->toBe('Investing');
});

it('renders the localized value inside a JsonResource', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    $payload = JsonResource::make($topic)->resolve();

    expect($payload['name'])->toBe('Investing');
});

it('keeps getTranslations returning the full map after the serialization change', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    expect($topic->getTranslations('name'))->toBe(['en' => 'Investing', 'sk' => 'Investovanie']);
});
