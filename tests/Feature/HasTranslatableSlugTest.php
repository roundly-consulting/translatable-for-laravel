<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\Tests\Fixtures\Article;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    $this->createTopicsTable();
    $this->createArticlesTable();
    app()->setLocale('en');
});

it('auto-generates a slug per locale from the source field', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    expect($topic->getTranslations('slug'))->toBe(['en' => 'investing', 'sk' => 'investovanie']);
});

it('preserves an admin-supplied slug and only fills the missing locale', function (): void {
    $topic = Topic::query()->create([
        'name' => ['en' => 'Investing', 'sk' => 'Investovanie'],
        'slug' => ['en' => 'custom-investing'],
    ]);

    expect($topic->getTranslations('slug'))->toBe(['en' => 'custom-investing', 'sk' => 'investovanie']);
});

it('suffixes per-locale collisions', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing']]);
    Topic::query()->create(['name' => ['en' => 'Investing']]);
    $third = Topic::query()->create(['name' => ['en' => 'Investing']]);

    expect($third->getTranslations('slug'))->toBe(['en' => 'investing-3']);
});

it('generates a random slug when a locale source slugifies to nothing', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => '###']]);

    expect($topic->getTranslations('slug')['en'])->toMatch('/^[a-z0-9]{8}$/');
});

it('generates no slug for a locale that has no source value', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Investing']]);

    // Only 'en' has a source, so only 'en' gets a slug.
    expect($topic->getTranslations('slug'))->toBe(['en' => 'investing']);
});

it('generates a single string slug for a plain model', function (): void {
    $article = Article::query()->create(['name' => 'Investing Basics']);

    expect($article->slug)->toBe('investing-basics');
});

it('preserves an admin single slug', function (): void {
    $article = Article::query()->create(['name' => 'Investing', 'slug' => 'my-custom']);

    expect($article->slug)->toBe('my-custom');
});

it('suffixes single slug collisions', function (): void {
    Article::query()->create(['name' => 'Investing']);
    $second = Article::query()->create(['name' => 'Investing']);

    expect($second->slug)->toBe('investing-2');
});

it('generates a random single slug with no source', function (): void {
    $article = Article::query()->create(['name' => '']);

    expect($article->slug)->toMatch('/^[a-z0-9]{8}$/');
});
