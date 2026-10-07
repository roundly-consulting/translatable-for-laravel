<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\Exceptions\TranslatableException;
use RoundlyConsulting\Translatable\Exceptions\TranslationsNotLoadedException;
use RoundlyConsulting\Translatable\Facades\Translatable;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

/**
 * A model loaded without its translatable column (`select('id')`, a cached catalog row) has no
 * key for it in `$attributes`. A per-locale write merges into the stored map, and the absent
 * key used to read as an empty map — so `setTranslation('name', 'sk', …)->save()` wrote
 * `{"sk": …}` over every other stored locale. Reads ignored
 * `Model::preventAccessingMissingAttributes()`, which `$model->name` honours.
 */
beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('en');
    config()->set('translatable.locales', ['en', 'sk']);

    $this->stored = ['en' => 'Investing', 'sk' => 'Investovanie', 'de' => 'Investieren'];
    $this->id = Topic::query()->create(['name' => $this->stored])->id;
});

afterEach(function (): void {
    Model::preventAccessingMissingAttributes(false);
});

/** @return array<string, string> */
function storedTopicName(int $id): array
{
    return Topic::query()->findOrFail($id)->getTranslations('name');
}

function partialTopic(): Topic
{
    return Topic::query()->select('id')->firstOrFail();
}

it('refuses a per-locale write on a model loaded without the column', function (string $write): void {
    $partial = partialTopic();

    $attempt = match ($write) {
        'setTranslation' => fn () => $partial->setTranslation('name', 'sk', 'X')->save(),
        'forgetTranslation' => fn () => $partial->forgetTranslation('name', 'de')->save(),
        'Translatable::apply' => function () use ($partial): void {
            Translatable::apply($partial, TranslationChanges::make(['name' => ['sk' => 'Y']]));
            $partial->save();
        },
        'a current-locale assignment' => function () use ($partial): void {
            $partial->name = 'Z';
            $partial->save();
        },
        'a JSON-path key' => fn () => $partial->update(['name->sk' => 'W']),
    };

    expect($attempt)->toThrow(TranslationsNotLoadedException::class, '[name]')
        ->and(storedTopicName($this->id))->toEqual($this->stored);
})->with(['setTranslation', 'forgetTranslation', 'Translatable::apply', 'a current-locale assignment', 'a JSON-path key']);

it('throws an exception the package base class catches', function (): void {
    expect(fn () => partialTopic()->setTranslation('name', 'sk', 'X'))->toThrow(TranslatableException::class);
});

it('still replaces the whole map on a model loaded without the column', function (): void {
    partialTopic()->setTranslations('name', ['en' => 'Saving'])->save();
    expect(storedTopicName($this->id))->toBe(['en' => 'Saving']);

    $partial = partialTopic();
    $partial->name = ['sk' => 'Sporenie'];
    $partial->save();
    expect(storedTopicName($this->id))->toBe(['sk' => 'Sporenie']);
});

it('still merges into a column the model was just created without', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Cooking']]);
    $topic->setTranslation('description', 'en', 'Recipes')->save();

    expect(Topic::query()->findOrFail($topic->id)->getTranslations('description'))->toBe(['en' => 'Recipes']);
});

it('still merges into a loaded column that is null', function (): void {
    $topic = Topic::query()->findOrFail($this->id);
    $topic->setTranslation('description', 'sk', 'Popis')->save();

    expect(Topic::query()->findOrFail($this->id)->getTranslations('description'))->toBe(['sk' => 'Popis']);
});

it('reads an unloaded column as empty when missing-attribute access is allowed', function (): void {
    $partial = partialTopic();

    expect($partial->getTranslations('name'))->toBe([])
        ->and($partial->hasTranslation('name', 'en'))->toBeFalse()
        ->and($partial->missingLocales('name'))->toBe(['en', 'sk'])
        ->and($partial->isFullyTranslated('name'))->toBeFalse()
        ->and($partial->getTranslation('name', 'en'))->toBeNull();
});

it('throws MissingAttributeException on reads under preventAccessingMissingAttributes', function (string $read): void {
    Model::preventAccessingMissingAttributes();
    $partial = partialTopic();

    $attempt = match ($read) {
        'getTranslations' => fn () => $partial->getTranslations('name'),
        'getTranslations for every field' => fn () => $partial->getTranslations(),
        'hasTranslation' => fn () => $partial->hasTranslation('name', 'en'),
        'missingLocales' => fn () => $partial->missingLocales('name'),
        'isFullyTranslated' => fn () => $partial->isFullyTranslated('name'),
        'getTranslation' => fn () => $partial->getTranslation('name', 'en'),
    };

    expect($attempt)->toThrow(MissingAttributeException::class);
})->with(['getTranslations', 'getTranslations for every field', 'hasTranslation', 'missingLocales', 'isFullyTranslated', 'getTranslation']);

it('keeps reading new and freshly created models under preventAccessingMissingAttributes', function (): void {
    Model::preventAccessingMissingAttributes();

    $created = Topic::query()->create(['name' => ['en' => 'Cooking']]);

    expect((new Topic)->getTranslations('name'))->toBe([])
        ->and($created->getTranslations('description'))->toBe([])
        ->and(Topic::query()->findOrFail($this->id)->getTranslation('name', 'sk'))->toBe('Investovanie');
});
