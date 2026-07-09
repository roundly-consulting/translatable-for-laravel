<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Translatable\Events\TranslationsChanged;
use RoundlyConsulting\Translatable\Tests\Fixtures\EventfulTopic;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('en');
});

it('fires once on create with the changed translatable attributes', function (): void {
    Event::fake([TranslationsChanged::class]);

    $topic = EventfulTopic::query()->create(['name' => ['en' => 'Investing']]);

    Event::assertDispatchedTimes(TranslationsChanged::class, 1);
    Event::assertDispatched(TranslationsChanged::class, function (TranslationsChanged $event) use ($topic): bool {
        return $event->model->is($topic) && $event->changedAttributes === ['name'];
    });
});

it('fires on a translated update with only the changed attribute', function (): void {
    $topic = EventfulTopic::query()->create(['name' => ['en' => 'Investing']]);

    Event::fake([TranslationsChanged::class]);

    $topic->setTranslation('name', 'sk', 'Investovanie');
    $topic->save();

    Event::assertDispatched(TranslationsChanged::class, function (TranslationsChanged $event): bool {
        return $event->changedAttributes === ['name'];
    });
});

it('stays silent when only a non-translatable column changes', function (): void {
    Schema::table('topics', function (Blueprint $table): void {
        $table->string('color')->nullable();
    });

    $topic = EventfulTopic::query()->create(['name' => ['en' => 'Investing']]);

    Event::fake([TranslationsChanged::class]);

    $topic->forceFill(['color' => 'red'])->save();

    Event::assertNotDispatched(TranslationsChanged::class);
});

it('does not fire for a model without the opt-in trait', function (): void {
    Event::fake([TranslationsChanged::class]);

    Topic::query()->create(['name' => ['en' => 'Investing']]);

    Event::assertNotDispatched(TranslationsChanged::class);
});

it('drives an AI-fill listener from the missing locales', function (): void {
    $seen = [];

    Event::listen(TranslationsChanged::class, function (TranslationsChanged $event) use (&$seen): void {
        foreach ($event->changedAttributes as $attribute) {
            $seen[$attribute] = $event->model instanceof EventfulTopic
                ? $event->model->missingLocales($attribute)
                : [];
        }
    });

    EventfulTopic::query()->create(['name' => ['en' => 'Investing']]);

    expect($seen)->toBe(['name' => ['sk']]);
});
