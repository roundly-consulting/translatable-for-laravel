<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\Tests\Fixtures\IdResolvableTopic;
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

it('does not fall back to the id by default', function (): void {
    $topic = Topic::query()->create(['name' => ['en' => 'Investing']]);

    // The numeric value matches no slug, and id fallback is off by default → no resolution.
    expect((new Topic)->resolveRouteBinding((string) $topic->id, 'slug'))->toBeNull();
});

it('resolves a numeric id only when the id fallback is opted in', function (): void {
    $topic = IdResolvableTopic::query()->create(['name' => ['en' => 'Investing']]);

    $resolved = (new IdResolvableTopic)->resolveRouteBinding((string) $topic->id, 'slug');

    expect($resolved?->id)->toBe($topic->id);
});

it('prefers a slug over the id when the id fallback is opted in', function (): void {
    // A record whose slug is the literal "2024" must win over the record with id 2024.
    $numericSlug = IdResolvableTopic::query()->create(['name' => ['en' => 'Year'], 'slug' => ['en' => '2024']]);

    $resolved = (new IdResolvableTopic)->resolveRouteBinding('2024', 'slug');

    expect($resolved?->id)->toBe($numericSlug->id);
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
