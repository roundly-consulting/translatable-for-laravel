<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;
use RoundlyConsulting\Translatable\Exceptions\TranslatableException;
use RoundlyConsulting\Translatable\Facades\Translatable;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

/**
 * A LIKE search term is user input, so `%`, `_` and `\` must be neutralised — but escaping
 * them is only half the job: SQLite and SQL Server have NO default LIKE escape character, so
 * the escape character has to be stated in the SQL. Without it the escaping backslashes stay
 * literal and a perfectly ordinary term ("100%", "a_b") matches NOTHING.
 *
 * Every expectation below is asserted on both a wildcard-free control (the wildcard must not
 * widen) and the literal row (the term must still find itself).
 */
function seedWildcardTopics(): void
{
    Topic::query()->create(['name' => ['en' => '100% cotton shirt']]);
    Topic::query()->create(['name' => ['en' => '100 percent cotton shirt']]);
    Topic::query()->create(['name' => ['en' => 'a_b literal']]);
    Topic::query()->create(['name' => ['en' => 'axb wildcarded']]);
    Topic::query()->create(['name' => ['en' => 'back\\slash']]);
    Topic::query()->create(['name' => ['en' => 'backXslash']]);
}

/** @return list<string> */
function searchNames(string $term): array
{
    return Translatable::search(
        Topic::query(),
        new TranslationSearch(fields: ['name'], term: $term),
    )->get()->map(static fn (Topic $topic): string => (string) $topic->getTranslation('name', 'en'))->all();
}

beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('en');
    seedWildcardTopics();
});

it('treats a percent sign in the term as a literal, not a wildcard', function (): void {
    expect(searchNames('100%'))->toBe(['100% cotton shirt']);
});

it('treats an underscore in the term as a literal, not a single-char wildcard', function (): void {
    expect(searchNames('a_b'))->toBe(['a_b literal']);
});

it('treats a backslash in the term as a literal', function (): void {
    expect(searchNames('back\\slash'))->toBe(['back\\slash']);
});

it('still matches an ordinary term case-insensitively', function (): void {
    expect(searchNames('COTTON'))->toHaveCount(2);
});

it('never lets a bare wildcard term match every row', function (): void {
    expect(searchNames('%'))->toBe(['100% cotton shirt']);
});

it('escapes wildcards on postgres too (ilike path)', function (): void {
    $this->app['config']->set('database.default', 'pgsql');
    Schema::connection('pgsql')->dropIfExists('topics');
    $this->createTopicsTable();
    seedWildcardTopics();

    expect(Schema::getConnection()->getDriverName())->toBe('pgsql')
        ->and(searchNames('100%'))->toBe(['100% cotton shirt'])
        ->and(searchNames('a_b'))->toBe(['a_b literal'])
        ->and(searchNames('back\\slash'))->toBe(['back\\slash'])
        ->and(searchNames('%'))->toBe(['100% cotton shirt'])
        ->and(searchNames('COTTON'))->toHaveCount(2);
})->skip(fn (): bool => ! pgsqlConfigured(), 'PostgreSQL is not configured.');

it('matches nothing when the search names no fields', function (): void {
    $results = Translatable::search(Topic::query(), new TranslationSearch(fields: [], term: 'zzz'));

    expect($results->count())->toBe(0)
        ->and(Topic::query()->count())->toBe(6);
});

it('matches nothing when no locale is supported', function (): void {
    config()->set('translatable.locales', []);

    expect(searchNames('cotton'))->toBe([]);
});

it('still validates the field names when no locale is supported', function (): void {
    config()->set('translatable.locales', []);

    expect(fn () => Translatable::search(
        Topic::query(),
        new TranslationSearch(fields: ['name; drop table topics'], term: 'x'),
    ))->toThrow(TranslatableException::class);
});

it('qualifies the column, so a search works on a join with another name column', function (): void {
    $this->createTopicTagsTable();
    DB::table('topic_tags')->insert(Topic::query()->pluck('id')->map(static fn (mixed $id): array => [
        'topic_id' => $id,
        'name' => 'cotton tag',
    ])->all());

    $query = Topic::query()->select('topics.*')->join('topic_tags', 'topic_tags.topic_id', '=', 'topics.id');

    expect(Translatable::search($query, new TranslationSearch(fields: ['name'], term: 'cotton'))
        ->get()
        ->map(static fn (Topic $topic): string => (string) $topic->getTranslation('name', 'en'))
        ->sort()
        ->values()
        ->all())->toBe(['100 percent cotton shirt', '100% cotton shirt']);
});
