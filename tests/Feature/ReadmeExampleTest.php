<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Translatable\Facades\Translatable;
use RoundlyConsulting\Translatable\Tests\Fixtures\ReadmeTopic;

/**
 * The README's usage example, run as written against a model that mirrors the README's
 * (`ReadmeTopic`). Every value asserted here is a value an inline README comment promises.
 */
beforeEach(function (): void {
    Schema::dropIfExists('readme_topics');

    Schema::create('readme_topics', function (Blueprint $table): void {
        $table->id();
        $table->translatable('name');   // a jsonb column
        $table->timestamps();
    });

    app()->setLocale('en');
});

it('runs the README usage example', function (): void {
    $topic = ReadmeTopic::create(['name' => ['en' => 'Investing']]);

    app()->setLocale('sk');
    expect($topic->name)->toBe('Investing')
        ->and($topic->missingLocales('name'))->toBe(['sk']);

    $topic->setTranslation('name', 'sk', 'Investovanie')->save();
    expect($topic->name)->toBe('Investovanie')
        ->and($topic->getTranslations('name'))->toBe(['en' => 'Investing', 'sk' => 'Investovanie']);

    expect(Translatable::usingLocale('en', fn (): string => $topic->name))->toBe('Investing');
});
