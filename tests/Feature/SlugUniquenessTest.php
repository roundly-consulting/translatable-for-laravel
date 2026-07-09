<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Translatable\DataTransferObjects\UniqueSlugContext;
use RoundlyConsulting\Translatable\Rules\UniqueTranslatedSlug;
use RoundlyConsulting\Translatable\Support\TranslatableSlug;
use RoundlyConsulting\Translatable\Tests\Fixtures\KeyedRecord;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('en');
});

function createKeyedRecordsTable(): void
{
    Schema::dropIfExists('keyed_records');

    Schema::create('keyed_records', function (Blueprint $table): void {
        $table->string('code')->primary();
        $table->jsonb('slug')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
}

it('detects a taken per-locale slug', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing']]);

    expect(TranslatableSlug::slugTaken('topics', 'slug', 'en', 'investing', null))->toBeTrue()
        ->and(TranslatableSlug::slugTaken('topics', 'slug', 'en', 'free', null))->toBeFalse();
});

it('ignores the given id', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Investing']]);

    expect(TranslatableSlug::slugTaken('topics', 'slug', 'en', 'investing', $topic->id))->toBeFalse();
});

it('excludes soft-deleted rows', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Investing']]);
    $topic->delete();

    expect(TranslatableSlug::slugTaken('topics', 'slug', 'en', 'investing', null))->toBeFalse();
});

it('adds per-locale errors via assertUnique', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing'], 'slug' => ['en' => 'investing']]);

    $validator = Validator::make([], []);
    TranslatableSlug::assertUnique($validator, new UniqueSlugContext(
        input: ['en' => 'investing'],
        table: 'topics',
    ));

    expect($validator->errors()->has('slug.en'))->toBeTrue();
});

it('passes assertUnique for a free slug', function (): void {
    $validator = Validator::make([], []);
    TranslatableSlug::assertUnique($validator, new UniqueSlugContext(
        input: ['en' => 'free-slug'],
        table: 'topics',
    ));

    expect($validator->errors()->isEmpty())->toBeTrue();
});

it('validates via the UniqueTranslatedSlug rule', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing'], 'slug' => ['en' => 'investing']]);

    $failing = Validator::make(
        ['slug' => ['en' => 'investing']],
        ['slug' => new UniqueTranslatedSlug('topics')],
    );

    $passing = Validator::make(
        ['slug' => ['en' => 'fresh']],
        ['slug' => new UniqueTranslatedSlug('topics')],
    );

    expect($failing->fails())->toBeTrue()
        ->and($passing->fails())->toBeFalse();
});

it('honours ignore-id in the rule', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Investing'], 'slug' => ['en' => 'investing']]);

    $validator = Validator::make(
        ['slug' => ['en' => 'investing']],
        ['slug' => new UniqueTranslatedSlug('topics', $topic->id)],
    );

    expect($validator->fails())->toBeFalse();
});

it('respects a non-id primary key when ignoring a row', function (): void {
    createKeyedRecordsTable();
    KeyedRecord::query()->create(['code' => 'abc', 'slug' => ['en' => 'investing']]);

    // A different string key still collides.
    expect(TranslatableSlug::slugTaken('keyed_records', 'slug', 'en', 'investing', 'xyz', 'code'))->toBeTrue()
        // Ignoring its own string key clears the collision.
        ->and(TranslatableSlug::slugTaken('keyed_records', 'slug', 'en', 'investing', 'abc', 'code'))->toBeFalse();
});

it('validates a custom key name through the rule and context', function (): void {
    createKeyedRecordsTable();
    KeyedRecord::query()->create(['code' => 'abc', 'slug' => ['en' => 'investing']]);

    $ignoringSelf = Validator::make(
        ['slug' => ['en' => 'investing']],
        ['slug' => new UniqueTranslatedSlug('keyed_records', 'abc', keyName: 'code')],
    );

    $validator = Validator::make([], []);
    TranslatableSlug::assertUnique($validator, new UniqueSlugContext(
        input: ['en' => 'investing'],
        table: 'keyed_records',
        keyName: 'code',
    ));

    expect($ignoringSelf->fails())->toBeFalse()
        ->and($validator->errors()->has('slug.en'))->toBeTrue();
});
