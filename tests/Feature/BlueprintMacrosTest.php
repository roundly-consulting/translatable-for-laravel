<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

it('creates translatable and slug columns via the blueprint macros', function (): void {
    Schema::dropIfExists('topics');

    Schema::create('topics', function (Blueprint $table): void {
        $table->id();
        $table->translatable('name');
        $table->translatableSlug();
        $table->timestamps();
        $table->softDeletes();
    });

    $topic = Topic::query()->create(['name' => ['en' => 'Investing']]);

    expect(Schema::hasColumns('topics', ['name', 'slug']))->toBeTrue()
        ->and($topic->getTranslations('slug'))->toBe(['en' => 'investing']);
});
