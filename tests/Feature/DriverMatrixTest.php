<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;
use RoundlyConsulting\Translatable\Support\ConnectionDriver;
use RoundlyConsulting\Translatable\Support\TranslatableSlug;
use RoundlyConsulting\Translatable\Support\Translations;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

/**
 * D — the driver matrix.
 *
 * Translatable is the package the whole DriverMatrix exists for. Bug #39 shipped from here:
 * a `LIKE` without an `ESCAPE` clause, which is **green on Postgres and matches nothing on
 * SQLite**. Note the direction, because it decides what a driver leg is worth: Postgres was
 * the GREEN one. A pgsql leg on its own would never have found #39.
 *
 * Re-measured on this row by re-introducing #39 verbatim (dropping ` escape ?` from
 * TranslationManager::whereLike):
 *
 *   | engine   | result                |
 *   |----------|-----------------------|
 *   | sqlite   | 4 FAILED  — caught    |
 *   | postgres | 6 passed  — NOT caught|
 *
 * So the value of a matrix is not "a real engine is more truthful than SQLite". It is that
 * the engines DISAGREE, and any single one of them — including a real one — can be the one
 * that stays green.
 */
beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('en');
});

/**
 * The driver-truth pin (Wave 2's lesson), and the one assertion that makes a lying leg
 * impossible. It compares the driver the ENVIRONMENT declares against what the connection
 * itself answers, so a "mysql" or "pgsql" job that quietly ran SQLite — a missing
 * `TESTING_DB_DRIVER`, a `defineEnvironment()` override without `parent::` — goes red here
 * rather than reporting a healthy green. It fires automatically, unlike reading a skip count
 * by hand.
 */
it('runs on the driver the environment declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()))
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});

/**
 * The package's own driver discrimination must agree with the engine on the wire. This is
 * the branch every divergent path in the package hangs off (`isPgsql() ? 'ilike' : 'like'`,
 * the functional slug indexes, the jsonb column type) — if it were ever wrong, every one of
 * them would be wrong at once, and on SQLite the mistake is invisible because the `like`
 * branch is also the SQLite branch.
 */
it('agrees with the engine about whether it is postgres', function (): void {
    expect(ConnectionDriver::isPgsql(DB::connection()))
        ->toBe(DriverMatrix::driver() === 'pgsql');
});

/**
 * The escape contract, asserted on whatever engine the leg configured — this is #39's own
 * test, generalised off SQLite.
 *
 * On the mysql leg this is the FIRST time the `like` branch (the non-pgsql branch) has ever
 * run against a real server rather than SQLite: MySQL and SQLite take the same code path
 * here, but they do not agree about escaping — SQLite has no default LIKE escape character
 * at all, while MySQL has one — so "the like branch works" was only ever proven against the
 * more forgiving of the two.
 */
it('treats wildcards in a search term as literals on the configured engine', function (): void {
    Topic::query()->create(['name' => ['en' => '100% cotton shirt']]);
    Topic::query()->create(['name' => ['en' => '100 percent cotton shirt']]);
    Topic::query()->create(['name' => ['en' => 'a_b literal']]);
    Topic::query()->create(['name' => ['en' => 'axb wildcarded']]);

    $search = static fn (string $term): array => Translations::whereLike(
        Topic::query(),
        new TranslationSearch(fields: ['name'], term: $term),
    )->get()->map(static fn (Topic $t): string => (string) $t->getTranslation('name', 'en'))->all();

    expect($search('100%'))->toBe(['100% cotton shirt'])
        ->and($search('a_b'))->toBe(['a_b literal'])
        // The bare wildcard must not widen to every row — the half that fails loudest when
        // the ESCAPE clause is missing.
        ->and($search('%'))->toBe(['100% cotton shirt']);
});

/**
 * The case-insensitivity contract, on whatever engine the leg configured.
 *
 * `TranslationManager::search()` documents itself as "a per-locale, CASE-INSENSITIVE search",
 * and that promise used to be inherited from the engine rather than stated in the SQL:
 * `ilike` is case-insensitive by definition, and SQLite's `like` is ASCII-case-insensitive by
 * default, so postgres and sqlite both flattered the code. MySQL does not — it extracts JSON
 * as `utf8mb4_bin`, a case-SENSITIVE collation — so `search('COTTON')` returned **zero rows**
 * there, on a package whose entire job is searching translated text.
 *
 * That is #39's shape exactly (a search silently matching nothing on one engine while green
 * on the others), which is the argument for the third leg in one test: two engines agreeing
 * is not evidence when they agree by coincidence.
 *
 * Every case here asserts BOTH that the term finds the row it should AND that it does not
 * widen — a `lower()` on both sides must not turn into a match-everything.
 */
it('searches case-insensitively on the configured engine', function (): void {
    Topic::query()->create(['name' => ['en' => '100% cotton shirt']]);
    Topic::query()->create(['name' => ['en' => 'COTTON canvas bag']]);
    Topic::query()->create(['name' => ['en' => 'linen towel']]);

    $search = static fn (string $term): array => Translations::whereLike(
        Topic::query(),
        new TranslationSearch(fields: ['name'], term: $term),
    )->get()->map(static fn (Topic $t): string => (string) $t->getTranslation('name', 'en'))->all();

    // Upper term finds the lower row, lower term finds the upper row, and both find both.
    expect($search('COTTON'))->toHaveCount(2)
        ->and($search('cotton'))->toHaveCount(2)
        ->and($search('CoTtOn'))->toHaveCount(2)
        // ...and case-insensitivity has not quietly become match-everything.
        ->and($search('linen'))->toBe(['linen towel']);
});

/**
 * `jsonb()` is a Blueprint macro this package ships. Postgres maps it to a real `jsonb`
 * column; every other driver gets `json`. Asserting the column is USABLE (a translated write
 * and read back) on whatever engine the leg configured is what proves the macro resolved to
 * something real rather than merely something creatable.
 */
it('round-trips a translated jsonb column on the configured engine', function (): void {
    $topic = Topic::query()->create([
        'name' => ['en' => 'Hello', 'sk' => 'Ahoj'],
        'description' => ['en' => 'A greeting'],
    ]);

    $fresh = $topic->fresh();

    expect($fresh->getTranslation('name', 'en'))->toBe('Hello')
        ->and($fresh->getTranslation('name', 'sk'))->toBe('Ahoj')
        ->and($fresh->getTranslation('description', 'en'))->toBe('A greeting');
});

/**
 * The functional per-locale unique indexes are Postgres-only by design — `TranslatableSlug`
 * returns early on any other driver. Both halves are pinned on the driver the leg is running,
 * so the mysql and sqlite legs prove the guard really no-ops rather than throwing, and the
 * pgsql leg proves the indexes really appear. Without the mysql leg the "no-op" half was only
 * ever asserted on SQLite.
 */
it('creates functional slug indexes only on postgres', function (): void {
    Schema::dropIfExists('topics');
    $this->createTopicsTable();

    TranslatableSlug::uniqueIndexes('topics');

    if (DriverMatrix::driver() !== 'pgsql') {
        // The point: on a non-postgres engine this must be a silent no-op, not an error —
        // and the table must survive it intact.
        expect(Schema::hasTable('topics'))->toBeTrue()
            ->and(Schema::hasColumn('topics', 'slug'))->toBeTrue();

        return;
    }

    $indexes = DB::table('pg_indexes')->where('tablename', 'topics')->pluck('indexname');

    expect($indexes->filter(static fn (string $n): bool => str_contains($n, 'slug')))->not->toBeEmpty();
});
