<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

it('creates translatable columns via the blueprint macro', function (): void {
    Schema::dropIfExists('topics');

    Schema::create('topics', function (Blueprint $table): void {
        $table->id();
        $table->translatable('name');
        $table->translatable('slug')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    $topic = Topic::query()->create(['name' => ['en' => 'Investing'], 'slug' => ['en' => 'investing']]);

    expect(Schema::hasColumns('topics', ['name', 'slug']))->toBeTrue()
        ->and($topic->fresh()?->getTranslations('name'))->toBe(['en' => 'Investing'])
        ->and($topic->fresh()?->getTranslations('slug'))->toBe(['en' => 'investing']);
});
