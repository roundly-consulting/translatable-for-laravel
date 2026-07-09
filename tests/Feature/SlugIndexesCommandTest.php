<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Translatable\Support\TranslatableSlug;

it('warns and no-ops on a non-postgres driver', function (): void {
    $this->createTopicsTable();

    $this->artisan('translatable:slug-indexes', ['table' => 'topics'])
        ->expectsOutputToContain('only runs on PostgreSQL')
        ->assertSuccessful();
});

it('is a no-op when uniqueIndexes runs on sqlite', function (): void {
    $this->createTopicsTable();

    TranslatableSlug::uniqueIndexes('topics');

    // No error, and the table is untouched.
    expect(Schema::hasTable('topics'))->toBeTrue();
});

it('builds a deterministic index name', function (): void {
    expect(TranslatableSlug::indexName('topics', 'slug', 'en'))->toBe('topics_slug_en_unique');
});

it('fails on a hostile column option', function (): void {
    $this->artisan('translatable:slug-indexes', ['table' => 'topics', '--column' => "slug'; DROP"])
        ->expectsOutputToContain('Invalid column name')
        ->assertFailed();
});
