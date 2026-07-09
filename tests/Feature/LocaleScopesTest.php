<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('en');
});

it('matches an exact per-locale value with whereLocale', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);
    Topic::query()->create(['name' => ['en' => 'Cooking']]);

    expect(Topic::query()->whereLocale('name', 'Investovanie', 'sk')->count())->toBe(1)
        ->and(Topic::query()->whereLocale('name', 'Cooking')->count())->toBe(1);
});

it('defaults whereLocale to the current app locale', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);
    app()->setLocale('sk');

    expect(Topic::query()->whereLocale('name', 'Investovanie')->count())->toBe(1)
        ->and(Topic::query()->whereLocale('name', 'Investing')->count())->toBe(0);
});

it('finds rows that have a locale with whereHasLocale', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);
    Topic::query()->create(['name' => ['en' => 'Cooking']]);

    $withSk = Topic::query()->whereHasLocale('name', 'sk')->get();

    expect($withSk)->toHaveCount(1)
        ->and($withSk->first()->getTranslation('name', 'en'))->toBe('Investing');
});

it('finds rows missing a locale with whereMissingLocale', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);
    Topic::query()->create(['name' => ['en' => 'Cooking']]);

    $missingSk = Topic::query()->whereMissingLocale('name', 'sk')->get();

    expect($missingSk)->toHaveCount(1)
        ->and($missingSk->first()->getTranslation('name', 'en'))->toBe('Cooking');
});

it('defaults the locale of the has/missing scopes to the app locale', function (): void {
    Topic::query()->create(['name' => ['sk' => 'Investovanie']]);
    app()->setLocale('en');

    expect(Topic::query()->whereHasLocale('name')->count())->toBe(0)
        ->and(Topic::query()->whereMissingLocale('name')->count())->toBe(1);
});
