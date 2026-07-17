<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Translatable\Support\TranslatableSlug;

/**
 * These two cases assert the NON-postgres branch, so they are gated on the driver the leg is
 * actually running rather than on the assumption that it is sqlite. Both were written when
 * the suite could only ever be sqlite; on the pgsql leg the package correctly DOES create the
 * indexes, and an ungated "it no-ops" is simply a false statement there.
 *
 * The postgres half of the same behaviour is pinned in DriverMatrixTest, per driver.
 */
function notPostgres(): bool
{
    return DriverMatrix::driver() !== 'pgsql';
}

it('warns and no-ops on a non-postgres driver', function (): void {
    $this->createTopicsTable();

    $this->artisan('translatable:slug-indexes', ['table' => 'topics'])
        ->expectsOutputToContain('only runs on PostgreSQL')
        ->assertSuccessful();
})->skip(fn (): bool => ! notPostgres(), 'runs on postgres: the command is expected to build the indexes, not warn');

it('is a no-op when uniqueIndexes runs on sqlite', function (): void {
    $this->createTopicsTable();

    TranslatableSlug::uniqueIndexes('topics');

    // No error, and the table is untouched.
    expect(Schema::hasTable('topics'))->toBeTrue();
})->skip(fn (): bool => ! notPostgres(), 'runs on postgres: uniqueIndexes is expected to create indexes, not no-op');

it('builds a deterministic index name', function (): void {
    expect(TranslatableSlug::indexName('topics', 'slug', 'en'))->toBe('topics_slug_en_unique');
});

it('fails on a hostile column option', function (): void {
    $this->artisan('translatable:slug-indexes', ['table' => 'topics', '--column' => "slug'; DROP"])
        ->expectsOutputToContain('Invalid column name')
        ->assertFailed();
});
