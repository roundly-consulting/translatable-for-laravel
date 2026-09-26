<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Sluggable\Contracts\ProvidesLocaleMaps;
use RoundlyConsulting\Sluggable\Contracts\SlugLocales;
use RoundlyConsulting\Sluggable\DataTransferObjects\SlugIndexSpec;
use RoundlyConsulting\Sluggable\Exceptions\InvalidSlugDefinitionException;
use RoundlyConsulting\Sluggable\Facades\Slugs;
use RoundlyConsulting\Sluggable\Schema\SlugIndexes;
use RoundlyConsulting\Sluggable\SluggableServiceProvider;
use RoundlyConsulting\Sluggable\Support\ConfigSlugLocales;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;
use RoundlyConsulting\Translatable\Exceptions\NotATranslatableAttributeException;
use RoundlyConsulting\Translatable\Support\TranslatableSlugLocales;
use RoundlyConsulting\Translatable\Tests\Fixtures\SluggedTopic;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;
use RoundlyConsulting\Translatable\Tests\Fixtures\UncontractedSluggedTopic;

beforeEach(function (): void {
    $this->createTopicsTable();
});

describe('the ProvidesLocaleMaps seam', function (): void {
    it('is part of the Translatable contract', function (): void {
        expect(new Topic)->toBeInstanceOf(ProvidesLocaleMaps::class);
    });

    it('treats exactly the translatable attributes as locale maps', function (): void {
        $topic = new Topic;

        expect($topic->isLocaleMapAttribute('name'))->toBeTrue()
            ->and($topic->isLocaleMapAttribute('slug'))->toBeTrue()
            ->and($topic->isLocaleMapAttribute('id'))->toBeFalse();
    });

    it('round-trips a map through the guarded write path', function (): void {
        $topic = (new Topic)->setLocaleMap('name', ['en' => 'Investing', 'sk' => 'Investovanie', 'de' => '']);

        expect($topic->getLocaleMap('name'))->toBe(['en' => 'Investing', 'sk' => 'Investovanie'])
            ->and($topic->getTranslations('name'))->toBe(['en' => 'Investing', 'sk' => 'Investovanie']);
    });

    it('guards non-translatable keys on read and write', function (): void {
        expect(fn () => (new Topic)->getLocaleMap('id'))->toThrow(NotATranslatableAttributeException::class)
            ->and(fn () => (new Topic)->setLocaleMap('id', ['en' => 'x']))->toThrow(NotATranslatableAttributeException::class);
    });

    it('rejects malformed locale keys written through the seam', function (): void {
        expect(fn () => (new Topic)->setLocaleMap('name', ['en"; drop' => 'x']))->toThrow(InvalidLocaleException::class);
    });
});

describe('HasTranslations + HasSlug', function (): void {
    it('generates a per-locale slug map with no cast', function (): void {
        $topic = SluggedTopic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

        expect($topic->getTranslations('slug'))->toBe(['en' => 'investing', 'sk' => 'investovanie'])
            ->and($topic->slugMap())->toBe(['en' => 'investing', 'sk' => 'investovanie'])
            ->and($topic->fresh()?->getTranslations('slug'))->toBe(['en' => 'investing', 'sk' => 'investovanie']);
    });

    it('builds each locale from its own source locale, not the request locale', function (): void {
        app()->setLocale('en');

        $topic = SluggedTopic::query()->create(['name' => ['en' => 'Health', 'sk' => 'Zdravie a výživa']]);

        expect($topic->slugFor('sk'))->toBe('zdravie-a-vyziva')
            ->and($topic->slugFor('en'))->toBe('health');
    });

    it('reads the slug attribute as the current-locale string like any translation', function (): void {
        $topic = SluggedTopic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

        app()->setLocale('sk');

        expect($topic->slug)->toBe('investovanie')
            ->and($topic->currentSlug())->toBe('investovanie');
    });

    it('suffixes a colliding slug per locale', function (): void {
        SluggedTopic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);
        $second = SluggedTopic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Sporenie']]);

        expect($second->getTranslations('slug'))->toBe(['en' => 'investing-2', 'sk' => 'sporenie']);
    });

    it('fills a missing locale on update and keeps the existing one', function (): void {
        $topic = SluggedTopic::query()->create(['name' => ['en' => 'Investing']]);

        $topic->setTranslation('name', 'sk', 'Investovanie')->save();

        expect($topic->getTranslations('slug'))->toBe(['en' => 'investing', 'sk' => 'investovanie']);
    });

    it('keeps the slug column out of translation completeness', function (): void {
        $topic = SluggedTopic::query()->create([
            'name' => ['en' => 'Investing', 'sk' => 'Investovanie'],
            'description' => ['en' => 'About', 'sk' => 'O'],
        ]);

        expect($topic->isFullyTranslated())->toBeTrue()
            ->and(array_keys($topic->missingTranslations()))->toBe(['name', 'description']);
    });

    it('finds a model by its slug in a given locale', function (): void {
        $topic = SluggedTopic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

        expect(SluggedTopic::query()->whereSlug('investovanie', locale: 'sk')->first()?->id)->toBe($topic->id)
            ->and(SluggedTopic::query()->whereSlugInAnyLocale('investing')->first()?->id)->toBe($topic->id);
    });

    it('refuses a model that uses HasTranslations without implementing Translatable', function (): void {
        expect(fn () => UncontractedSluggedTopic::query()->create(['name' => ['en' => 'Investing']]))
            ->toThrow(InvalidSlugDefinitionException::class, 'ProvidesLocaleMaps');
    });

    it('enforces strict_locales on every sluggable write', function (): void {
        $topic = Slugs::withoutGeneration(
            fn (): SluggedTopic => SluggedTopic::query()->create(['name' => ['en' => 'Investing', 'de' => 'Investieren']]),
        );

        config()->set('translatable.strict_locales', true);

        expect(fn () => $topic->regenerateSlugs())->toThrow(InvalidLocaleException::class);
    });

    it('resolves a route binding by the current-locale slug', function (): void {
        Route::middleware(SubstituteBindings::class)
            ->get('/topics/{topic}', fn (SluggedTopic $topic): int => $topic->id)
            ->name('topics.show');

        $topic = SluggedTopic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

        app()->setLocale('sk');

        expect(route('topics.show', $topic))->toEndWith('/topics/investovanie');

        $this->get('/topics/investovanie')->assertOk()->assertSee((string) $topic->id);
        $this->get('/topics/'.$topic->id)->assertNotFound();
    });
});

describe('the SlugLocales binding', function (): void {
    it('resolves to the translatable adapter', function (): void {
        $locales = app(SlugLocales::class);

        expect($locales)->toBeInstanceOf(TranslatableSlugLocales::class)
            ->and($locales->supported())->toBe(['en', 'sk'])
            ->and($locales->fallback())->toBe(config('translatable.fallback_locale'))
            ->and($locales->current())->toBe(app()->getLocale());
    });

    it('follows translatable config and a rebound SupportedLocales', function (): void {
        config()->set('translatable.fallback_locale', 'sk');
        app()->bind(SupportedLocales::class, fn (): SupportedLocales => new class implements SupportedLocales
        {
            public function supported(): array
            {
                return ['en', 'sk', 'cs'];
            }
        });
        app()->setLocale('cs');

        $locales = app(SlugLocales::class);

        expect($locales->supported())->toBe(['en', 'sk', 'cs'])
            ->and($locales->fallback())->toBe('sk')
            ->and($locales->current())->toBe('cs');
    });

    it('wins regardless of provider order', function (): void {
        // sluggable registering AFTER translatable must not take the binding back (bindIf).
        (new SluggableServiceProvider(app()))->register();

        expect(app(SlugLocales::class))->toBeInstanceOf(TranslatableSlugLocales::class);
    });

    it('yields to a host binding registered in a later provider', function (): void {
        app()->bind(SlugLocales::class, ConfigSlugLocales::class);

        expect(app(SlugLocales::class))->toBeInstanceOf(ConfigSlugLocales::class);
    });

    it('treats an unset fallback locale as no fallback, so slug lookups keep working', function (): void {
        // `null` is a state the about section reports as DEFAULT; sluggable's contract spells
        // "no fallback" as null, and an empty string reached its JSON-path guard and threw.
        config()->set('translatable.fallback_locale', null);

        $topic = SluggedTopic::query()->create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);

        app()->setLocale('sk');

        expect(app(SlugLocales::class)->fallback())->toBeNull()
            ->and(SluggedTopic::query()->whereSlug('investovanie')->first()?->id)->toBe($topic->id)
            ->and(SluggedTopic::findBySlug('investing')?->id)->toBe($topic->id)
            ->and((new SluggedTopic)->resolveRouteBinding('investovanie')?->id)->toBe($topic->id);
    });

    it('refuses a malformed supported locale before it reaches index DDL', function (): void {
        // The removed TranslatableSlug::uniqueIndexes() allowlisted every locale before its DDL;
        // sluggable reads the list from this adapter when a spec names none.
        config()->set('translatable.locales', ['en', "sk'); drop table topics; --"]);

        SlugIndexes::plan(SlugIndexSpec::localeMap('topics', 'slug'));
    })->throws(InvalidLocaleException::class);
});
