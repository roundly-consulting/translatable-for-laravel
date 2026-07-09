<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Translatable\Tests\Fixtures\Article;

beforeEach(function (): void {
    app()->setLocale('en');
});

function createUniqueArticlesTable(bool $uniqueName = false): void
{
    Schema::dropIfExists('articles');

    Schema::create('articles', function (Blueprint $table) use ($uniqueName): void {
        $table->id();
        $table->string('name');
        $table->string('slug')->nullable()->unique();
        $table->timestamps();
        $table->softDeletes();

        if ($uniqueName) {
            $table->unique('name', 'articles_name_unique');
        }
    });
}

it('retries a single slug with the next suffix when a concurrent insert steals it', function (): void {
    createUniqueArticlesTable();

    // Boot the model so the trait's slug-generation listener is registered BEFORE the steal
    // listener below, guaranteeing generation runs first.
    new Article;

    // Simulate a race: after this Article mints 'investing', a competitor commits the same
    // slug before the insert lands, so the unique constraint rejects it and we must retry.
    Article::creating(function (Article $model): void {
        if ($model->slug === 'investing' && Article::query()->where('slug', 'investing')->doesntExist()) {
            Article::withoutEvents(fn () => Article::query()->create(['name' => 'Stolen', 'slug' => 'investing']));
        }
    });

    $article = Article::query()->create(['name' => 'Investing']);

    expect($article->slug)->toBe('investing-2')
        ->and(Article::query()->count())->toBe(2);
});

it('rethrows a unique violation that is not about the slug column', function (): void {
    createUniqueArticlesTable(uniqueName: true);

    Article::query()->create(['name' => 'Duplicate']);

    expect(fn () => Article::query()->create(['name' => 'Duplicate']))
        ->toThrow(QueryException::class);
});
