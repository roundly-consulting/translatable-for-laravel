<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\Facades\Translatable;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

/**
 * The recipes in the technical docs ("Ingesting form input"), copied here verbatim so they stay
 * true. Keep each copy identical to the docs.
 */
beforeEach(function (): void {
    $this->createTopicsTable();
    app()->setLocale('sk');
    config()->set('translatable.locales', ['en', 'sk']);

    $this->topic = Topic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);
});

/** @return array<string, string> */
function recipeName(Topic $topic): array
{
    $map = Topic::query()->findOrFail($topic->id)->getTranslations('name');
    ksort($map);

    return $map;
}

it('replaces the map from a full form, so a cleared locale is removed', function (): void {
    $topic = $this->topic;
    $request = Request::create('/topics/1', 'PUT', ['name' => ['en' => 'Investing', 'sk' => '']]);

    // docs: full form
    $request->validate(Translatable::rules('name', required: true));

    $topic->setTranslations('name', Translatable::fromInput($request->input('name')))->save();
    // end docs

    expect(recipeName($topic))->toBe(['en' => 'Investing']);
});

it('merges a partial update, so untouched locales are kept', function (mixed $input, array $expected): void {
    $topic = $this->topic;
    $request = Request::create('/topics/1', 'PATCH', $input === null ? [] : ['name' => $input]);

    // docs: partial update
    Translatable::apply($topic, TranslationChanges::make([
        'name' => Translatable::fromInput($request->input('name')),
    ]));
    $topic->save();
    // end docs

    expect(recipeName($topic))->toBe($expected);
})->with([
    'an absent field' => [null, ['en' => 'Investing', 'sk' => 'Investovanie']],
    'a bare string for the current locale' => ['Investície', ['en' => 'Investing', 'sk' => 'Investície']],
    'one locale of the map' => [['en' => 'Saving'], ['en' => 'Saving', 'sk' => 'Investovanie']],
    'a blank locale (a merge never clears one)' => [['sk' => ''], ['en' => 'Investing', 'sk' => 'Investovanie']],
]);
