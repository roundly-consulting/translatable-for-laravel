<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Translatable\Support\TranslatableSlug;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

pest()->group('pgsql');

beforeEach(function (): void {
    if (! pgsqlConfigured()) {
        $this->markTestSkipped('No PostgreSQL connection available; run the test-pgsql leg (TESTING_DB_DRIVER=pgsql).');
    }

    config()->set('database.default', 'pgsql');
    DB::setDefaultConnection('pgsql');

    Schema::connection('pgsql')->dropIfExists('topics');
    Schema::connection('pgsql')->create('topics', function (Blueprint $table): void {
        $table->id();
        $table->jsonb('name');
        $table->jsonb('description')->nullable();
        $table->jsonb('slug')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    TranslatableSlug::uniqueIndexes('topics');
});

afterEach(function (): void {
    if (pgsqlConfigured()) {
        Schema::connection('pgsql')->dropIfExists('topics');
    }
});

it('creates one functional unique index per locale', function (): void {
    $indexes = DB::connection('pgsql')
        ->table('pg_indexes')
        ->where('tablename', 'topics')
        ->pluck('indexname')
        ->all();

    expect($indexes)->toContain('topics_slug_en_unique')
        ->and($indexes)->toContain('topics_slug_sk_unique');
});

it('rejects a duplicate slug in the same locale', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing'], 'slug' => ['en' => 'investing']]);

    expect(fn () => DB::connection('pgsql')->table('topics')->insert([
        'name' => json_encode(['en' => 'Other']),
        'slug' => json_encode(['en' => 'investing']),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows the same slug in different locales', function (): void {
    Topic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investing'], 'slug' => ['en' => 'investing', 'sk' => 'investing']]);

    expect(Topic::query()->count())->toBe(1);
});

it('exempts a NULL (missing) locale from the unique index', function (): void {
    Topic::query()->create(['name' => ['en' => 'A'], 'slug' => ['en' => 'a']]);
    Topic::query()->create(['name' => ['sk' => 'B'], 'slug' => ['sk' => 'b']]);

    // Neither has an 'sk'/'en' collision respectively — both persist.
    expect(Topic::query()->count())->toBe(2);
});

it('is idempotent on a second uniqueIndexes run', function (): void {
    TranslatableSlug::uniqueIndexes('topics');

    expect(true)->toBeTrue();
});

it('rescues a stale-locale slug via whereAnySlug on pgsql', function (): void {
    app()->setLocale('en');
    config()->set('translatable.fallback_locale', 'en');
    $topic = Topic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

    $resolved = (new Topic)->resolveRouteBinding('investovanie', 'slug');

    expect($resolved?->id)->toBe($topic->id);
});

it('retries with the next suffix when a concurrent insert steals the slug', function (): void {
    app()->setLocale('en');
    config()->set('translatable.fallback_locale', 'en');

    // Boot the model so the trait's slug-generation listener registers before the steal one.
    new Topic;

    // Simulate a TOCTOU race: after this model generates 'investing' but before its own
    // insert, a competing writer commits the same slug. The unique index rejects the insert,
    // and the trait must retry with the next free suffix.
    Topic::creating(function (Topic $model): void {
        $slug = $model->getTranslations('slug')['en'] ?? null;

        if ($slug === 'investing' && DB::connection('pgsql')->table('topics')->where('slug->en', 'investing')->doesntExist()) {
            DB::connection('pgsql')->table('topics')->insert([
                'name' => json_encode(['en' => 'Stolen']),
                'slug' => json_encode(['en' => 'investing']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    $topic = Topic::query()->create(['name' => ['en' => 'Investing']]);

    expect($topic->getTranslations('slug')['en'])->toBe('investing-2')
        ->and(Topic::query()->count())->toBe(2);
});

it('runs the slug-indexes command on pgsql', function (): void {
    Schema::connection('pgsql')->table('topics', function (Blueprint $table): void {
        //
    });

    $this->artisan('translatable:slug-indexes', ['table' => 'topics'])
        ->expectsOutputToContain('Ensured per-locale unique slug indexes')
        ->assertSuccessful();
});
