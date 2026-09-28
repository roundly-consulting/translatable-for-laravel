<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Translatable\Events\TranslationsChanged;
use RoundlyConsulting\Translatable\Tests\Fixtures\EventfulTopic;

/**
 * PostgreSQL `jsonb` and MySQL `JSON` hand a stored map back re-spaced and key-reordered
 * (`{"en": "Investing", "sk": "Investovanie"}`), while the trait writes compact JSON in the
 * caller's key order. Eloquent compares the raw strings, so an untouched map read as "changed"
 * on those engines: a spurious UPDATE, a bumped `updated_at` and a spurious TranslationsChanged.
 *
 * Seeding the normalised form directly makes the engine's behaviour deterministic on every leg,
 * sqlite included.
 */
beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('en');
});

function seedNormalizedTopic(): EventfulTopic
{
    $id = DB::table('topics')->insertGetId([
        'name' => '{"sk": "Investovanie", "en": "Investing"}',
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return EventfulTopic::query()->findOrFail($id);
}

/** @return list<string> */
function recordUpdates(Closure $callback): array
{
    $updates = [];

    DB::listen(function ($query) use (&$updates): void {
        if (str_starts_with(strtolower($query->sql), 'update')) {
            $updates[] = $query->sql;
        }
    });

    $callback();

    return $updates;
}

it('does not mark an unchanged translation dirty when the engine normalises JSON', function (): void {
    $topic = seedNormalizedTopic();

    $topic->setTranslation('name', 'en', 'Investing');

    expect($topic->isDirty('name'))->toBeFalse()
        ->and($topic->isDirty())->toBeFalse();
});

it('does not save or fire an event when the unchanged map is re-submitted', function (): void {
    $topic = seedNormalizedTopic();
    Event::fake([TranslationsChanged::class]);

    $updates = recordUpdates(fn () => $topic->fill(['name' => $topic->getTranslations('name')])->save());

    expect($updates)->toBe([])
        ->and($topic->wasChanged())->toBeFalse();
    Event::assertNotDispatched(TranslationsChanged::class);
});

it('treats a NULL column and an empty map as the same absence of translations', function (): void {
    $topic = seedNormalizedTopic();

    $topic->setTranslations('description', []);

    expect($topic->isDirty('description'))->toBeFalse();
});

it('still saves and reports a real translation change', function (): void {
    $topic = seedNormalizedTopic();
    Event::fake([TranslationsChanged::class]);

    $topic->setTranslation('name', 'sk', 'Sporenie');

    expect($topic->isDirty('name'))->toBeTrue()
        ->and(recordUpdates(fn () => $topic->save()))->toHaveCount(1)
        ->and(EventfulTopic::query()->findOrFail($topic->id)->getTranslations('name'))
        ->toMatchArray(['en' => 'Investing', 'sk' => 'Sporenie']);
    Event::assertDispatched(
        TranslationsChanged::class,
        fn (TranslationsChanged $event): bool => $event->changedAttributes === ['name'],
    );
});

it('reports a forgotten locale as a change', function (): void {
    $topic = seedNormalizedTopic();

    $topic->forgetTranslation('name', 'sk');

    expect($topic->isDirty('name'))->toBeTrue();
});

it('compares a stored value that is not a JSON object raw', function (): void {
    $id = DB::table('topics')->insertGetId([
        'name' => '"Investing"', // a legacy scalar document, not a locale map
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $topic = EventfulTopic::query()->findOrFail($id);

    $topic->setTranslation('name', 'en', 'Investing');

    expect($topic->isDirty('name'))->toBeTrue();
});
