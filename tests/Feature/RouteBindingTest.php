<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('en');
    config()->set('translatable.fallback_locale', 'en');
});

it('resolves a slug in the request locale', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    $resolved = (new Topic)->resolveRouteBinding('investing', 'slug');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($topic->id);
});

it('falls back to the fallback-locale slug', function (): void {
    app()->setLocale('sk');
    $topic = Topic::query()->create(['name' => ['en' => 'Investing']]);

    // Only an English slug exists; request locale sk resolves via the en fallback.
    $resolved = (new Topic)->resolveRouteBinding('investing', 'slug');

    expect($resolved?->id)->toBe($topic->id);
});

it('rescues a stale-locale slug via whereAnySlug', function (): void {
    app()->setLocale('en');
    config()->set('translatable.fallback_locale', 'en');
    $topic = Topic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    // 'investovanie' is neither the request (en) nor fallback (en) slug — any-locale rescue.
    $resolved = (new Topic)->resolveRouteBinding('investovanie', 'slug');

    expect($resolved?->id)->toBe($topic->id);
});

it('resolves a numeric id', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Investing']]);

    $resolved = (new Topic)->resolveRouteBinding((string) $topic->id, 'slug');

    expect($resolved?->id)->toBe($topic->id);
});

it('returns null for an unknown slug', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing']]);

    expect((new Topic)->resolveRouteBinding('missing', 'slug'))->toBeNull();
});

it('delegates non-slug fields to the parent resolver', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Investing']]);

    $resolved = (new Topic)->resolveRouteBinding($topic->id, 'id');

    expect($resolved?->id)->toBe($topic->id);
});
