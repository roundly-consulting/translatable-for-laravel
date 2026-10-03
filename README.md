<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/translatable-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=translatable-for-laravel">
    <img src="art/hero.png" alt="Translatable for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/translatable-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/translatable-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/translatable-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/translatable-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/translatable-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/translatable-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=translatable-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Translatable for Laravel

Locale-map (`jsonb`) translatable attributes with a fallback chain for Eloquent — native, with
zero third-party dependencies.

A single `json`/`jsonb` column stores a plain `{ "en": "…", "sk": "…" }` map per attribute.
Reads return the current locale through a configurable fallback chain so content never renders
blank. Per-locale slugs come from
[`sluggable-for-laravel`](https://github.com/roundly-consulting/sluggable-for-laravel), which
reads and writes translatable attributes through this package's contract (see
[Slugs](#slugs)). This is the org-wide native solution for multi-locale Eloquent attributes.

## Requirements

- PHP 8.4+
- Laravel 12 or 13
- `roundly-consulting/enums-for-laravel` (installed automatically) — backs the `FallbackMode` enum
- `roundly-consulting/package-toolkit-for-laravel` (installed automatically) — the service-provider
  builder, the `DatabaseDriver` enum, and the `LIKE` escaper behind the search helper
- `roundly-consulting/sluggable-for-laravel` (installed automatically) — per-locale slugs over
  translatable attributes; this package's `Translatable` contract extends its
  `ProvidesLocaleMaps`
- Any Laravel-supported database (`ilike` is used on PostgreSQL, an escaped `like` elsewhere).

## Installation

```bash
composer require roundly-consulting/translatable-for-laravel
```

Optionally publish the config and the validation language lines:

```bash
php artisan vendor:publish --tag="translatable-config"
php artisan vendor:publish --tag="translatable-translations"
```

There is **no** migrations tag — you write your own tables using the `translatable()` column
macro below.

## Configuration

`config/translatable.php`:

```php
return [
    // Locale used when a requested locale has no value (FallbackMode::Fallback / Any, step 2).
    'fallback_locale' => env('TRANSLATABLE_FALLBACK_LOCALE', config('app.fallback_locale', 'en')),

    // How far the fallback chain reaches. none = exact only; fallback = exact -> fallback
    // locale; any = exact -> fallback locale -> first available (content never renders blank).
    // NOTE: `any` can surface a value from another locale when the requested + fallback
    // locales are empty — a cross-locale disclosure. See "Fallback modes" below.
    // Anything other than none/fallback/any throws rather than silently widening to `any`.
    'fallback' => env('TRANSLATABLE_FALLBACK', FallbackMode::Any),

    // Reject any locale key not in the supported list on writes (malformed keys are always
    // rejected regardless). Off by default, so any well-formed locale is accepted.
    // Env words are read as booleans (on/off, yes/no, true/false, 1/0); anything else throws.
    'strict_locales' => env('TRANSLATABLE_STRICT_LOCALES', false),

    // Default supported locales. Hosts SHOULD rebind SupportedLocales to their own source.
    'locales' => ['en', 'sk'],
];
```

| Key | Type | Default | Env | Purpose |
|-----|------|---------|-----|---------|
| `fallback_locale` | `string` | `app.fallback_locale` / `en` | `TRANSLATABLE_FALLBACK_LOCALE` | Locale tried after the exact one. Empty = no fallback locale. |
| `fallback` | `FallbackMode\|string` | `FallbackMode::Any` | `TRANSLATABLE_FALLBACK` (`none`/`fallback`/`any`) | How far the fallback chain reaches. Any other value throws the toolkit's `InvalidConfigurationException` on the first read. |
| `strict_locales` | `bool` | `false` | `TRANSLATABLE_STRICT_LOCALES` (`on`/`off`, `yes`/`no`, `true`/`false`, `1`/`0`) | Reject writes for locales outside the supported list. Any other value throws the toolkit's `InvalidConfigurationException` on the first write. |
| `locales` | `list<string>` | `['en', 'sk']` | — | Default supported locales. |

The package works with **zero** host configuration. Slug options (separator, word cap, reserved
words, …) live in `config/sluggable.php`.

### One source of truth for locales

Bind the `SupportedLocales` contract in your app's service provider to wrap a single source of
truth (used by `Translatable::supported()`, `isSupported()`, `fromInput()`, `rules()`,
`missingLocales()` — and by sluggable, see below):

```php
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;

$this->app->bind(SupportedLocales::class, fn () => new class implements SupportedLocales {
    /** @return list<string> */
    public function supported(): array
    {
        return App\Localization\Locale::SUPPORTED;
    }
});
```

Translatable also binds sluggable's `SlugLocales` contract to `TranslatableSlugLocales`, an adapter
over the same `SupportedLocales`, `translatable.fallback_locale` and the app locale — so slugs are
generated, indexed and bound for exactly the locales your translations use. Sluggable registers
its own default with `bindIf()`, so provider order doesn't matter; your own `SlugLocales` binding
in a later provider wins over both.

## Usage

### The `Translatable` facade

Everything that is not per-model state lives on the `Translatable` facade, over the injectable
`TranslationManager` — the model trait below reads its locale set, fallback settings, locale-key
validation and map resolution through the same manager.

```php
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Facades\Translatable;

// Locales
Translatable::supported();                        // ['en', 'sk'] — from the bound SupportedLocales
Translatable::isSupported('sk');                  // true — exact, case-sensitive
Translatable::currentLocale();                    // app()->getLocale()
Translatable::ensureLocale($locale);              // returns it, or throws InvalidLocaleException (malformed)
Translatable::ensureLocale($locale, strict: true); // … also throws when it is not supported

// Fallback chain
Translatable::fallbackMode();                     // FallbackMode, from config
Translatable::fallbackLocale();                   // 'en'
Translatable::resolve(['en' => 'Investing', 'sk' => 'Investovanie']);        // current locale + configured fallback
Translatable::resolve($map, 'de', FallbackMode::Fallback, fallbackLocale: 'sk');

// Admin input
Translatable::fromInput($request->input('name')); // clean locale map (see below)
Translatable::rules('name', required: true, each: ['max:120']);
Translatable::filledRule('name');                 // just the "at least one locale" rule
Translatable::apply($topic, TranslationChanges::make(['name' => ['sk' => 'Sporenie']])); // PATCH-merge

// Queries and locale scoping
Translatable::search(Topic::query(), new TranslationSearch(fields: ['name'], term: 'invest'));
Translatable::usingLocale('sk', fn (): string => $topic->name);
```

`resolve()` is the same resolution a model read uses, for a raw map you hold outside a model (a
cached payload, an API response, a config array): the requested locale (default: the current
one), then — per the mode — the fallback locale, then the first non-blank value in
supported-locale order. Mode and fallback locale default to the configured ones. Blank values
never win: `null`, `''`, booleans, arrays and objects are skipped, ints and floats are cast to
strings — a messy map resolves, it never throws:

```php
Translatable::resolve(['en' => null, 'sk' => 'Ahoj'], 'de'); // 'Ahoj'
Translatable::resolve(['sk' => 5], 'de');                   // '5'
```

#### Without the facade

Inject the manager — it is the facade's root, bound as a singleton, and exposes the identical
API:

```php
use RoundlyConsulting\Translatable\Support\TranslationManager;

final class UpdateTopicTranslations
{
    public function __construct(private TranslationManager $translations) {}

    public function handle(Topic $topic, array $input): void
    {
        $topic->setTranslations('name', $this->translations->fromInput($input['name'] ?? []));
    }
}
```

There are no action classes: the package does pure locale-map computation, which the manager
serves directly.

#### Testing: no fake, by design

The facade has no `fake()`. Nothing on it writes to the database, queues, mails, dispatches an
event or calls out, so there is nothing for a fake to record. To pin behaviour in a test:

```php
config()->set('translatable.locales', ['en', 'de']);      // the locale set
config()->set('translatable.fallback', FallbackMode::None); // the fallback chain

// or override one method — facade calls and every model observe the subclass:
Translatable::swap(new class extends TranslationManager {
    public function supported(): array { return ['en', 'de']; }
});
```

### Translatable attributes

List the translatable attributes in a `public array $translatable` property and implement the
`Translatable` contract (the trait provides every method). The model needs **no** `array` cast —
the trait owns JSON serialization for its listed attributes.

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

final class Topic extends Model implements Translatable
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];
}
```

```php
$topic->name;                                 // localized, fallback chain
$topic->getTranslation('name', 'sk');         // explicit locale, fallback on
$topic->getTranslation('name', 'sk', false);  // no fallback -> null if missing
$topic->translatedOrNull('name');             // app locale, NO fallback
$topic->getTranslations('name');              // full map (admin resources)
$topic->getTranslations();                    // every translatable attr -> its map
$topic->setTranslation('name', 'sk', 'Investovanie'); // fluent, returns $this
$topic->setTranslation('name', 'sk', null);   // forgets sk (never stores blank)
$topic->setTranslations('name', ['en' => 'Investing', 'sk' => 'Investovanie']);
$topic->replaceTranslations(['name' => ['en' => 'Investing']]);
$topic->forgetTranslation('name', 'sk');
$topic->forgetAllTranslations('name');
$topic->hasTranslation('name', 'sk');         // bool
$topic->getTranslatedLocales('name');         // list<string>
$topic->missingLocales('name');               // supported - translated (feed an AI service)
$topic->isTranslatableAttribute('name');      // bool
$topic->getTranslatableAttributes();          // list<string>
$topic->name = 'Investing';                   // sets the APP locale value
$topic->name = ['en' => 'Investing', 'sk' => 'Investovanie']; // replaces the map
$topic->update(['name->sk' => 'Sporenie']);   // JSON-path key: patches one locale, same guards
```

Blank/`null` values are dropped, so a map never stores an empty string and the fallback chain
always has something real to fall back to.

### Locale keys are validated on write

Every model write path (`$model->name = …`, `setTranslation`, `setTranslations`, mass assignment
— including JSON-path keys such as `update(['name->de' => …])`) validates its locale **keys**.
Malformed keys — anything with quotes, spaces, markup or SQL, e.g. a mass-assigned
`name[<script>]=…` — are rejected with an `InvalidLocaleException`; values must be strings, or
ints/floats (cast to string), so a nested array, a boolean or an object raises an
`InvalidTranslationValueException` instead of being stored. Turn on `strict_locales` to also reject
well-formed keys that aren't in your supported list. (A query-builder update such as
`Topic::query()->update([...])` never builds a model, so it bypasses these guards like any other
Eloquent attribute logic.)

**Route raw request maps through `Translatable::fromInput()`** — it drops unsupported and blank
locales *before* they reach the model (a bare string maps to the current locale, and is dropped
too when that locale isn't supported), so untrusted `$request->input('name')` never triggers a
write-path exception:

```php
$topic->setTranslations('name', Translatable::fromInput($request->input('name')));
// or validate first with Translatable::rules('name', required: true)
```

Validating a single key yourself (a `?locale=` query parameter, a route segment) is
`Translatable::ensureLocale($locale)` — or `Translatable::isSupported($locale)` for a boolean.

### Serialization (`toArray` / `toJson` / API Resources)

`toArray()`, `toJson()`, `response()->json($model)`, and API Resources emit the **resolved locale
value** for each translatable attribute — identical to `$model->name` — not the raw stored JSON
map:

```php
$topic->toArray()['name'];   // "Investing"  (localized, through the fallback chain)
$topic->toJson();            // {"name":"Investing", …}
TopicResource::make($topic); // {"name":"Investing"}
```

The effective `FallbackMode` is honoured (a `None` model with only `sk` set serializes `name` as
`null` for an `en` request). Persisted data is untouched — this changes output shape only. When an
admin screen needs the full map, call `getTranslations('name')` explicitly.

### Whole-model translation status

For admin completeness UIs, query status across every translatable attribute in one call:

```php
$topic->missingTranslations();     // ['description' => ['sk']]  field => missing locales
$topic->isFullyTranslated();       // bool — every field has every supported locale
$topic->isFullyTranslated('name'); // bool — one attribute
$topic->translationCompleteness(); // 0.0–1.0 — filled / (fields × locales), for a progress bar
```

Exclude a column (e.g. the slug) from a "content complete" bar by overriding a protected method:

```php
/** @return list<string> */
protected function translationStatusExcludes(): array
{
    return ['slug'];
}
```

### Translation-changed event (opt-in)

Add the `DispatchesTranslationEvents` trait to have the model dispatch a single
`TranslationsChanged` event once per persisted save when any translatable attribute changed —
ideal for queuing an AI service to fill `missingLocales()` or busting a cache, without overriding
the model. The base `HasTranslations` trait stays side-effect-free; nothing fires unless you opt in.

"Changed" means the decoded map changed: PostgreSQL and MySQL hand JSON back re-spaced and
key-reordered, and re-saving an unchanged map is neither an UPDATE nor an event on any engine.

```php
use RoundlyConsulting\Translatable\Concerns\DispatchesTranslationEvents;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;

final class Topic extends Model implements Translatable
{
    use HasTranslations;
    use DispatchesTranslationEvents;
    // …
}
```

```php
use RoundlyConsulting\Translatable\Events\TranslationsChanged;

Event::listen(TranslationsChanged::class, function (TranslationsChanged $event): void {
    foreach ($event->changedAttributes as $attribute) {          // list<string>
        if ($event->model->missingLocales($attribute) !== []) {
            FillMissingTranslations::dispatch($event->model, $attribute);
        }
    }
});
```

### Query scopes

Driver-agnostic JSON-path scopes over supported locales (work on any Laravel database):

```php
Topic::query()->whereLocale('name', 'Investing', 'en'); // exact per-locale value (locale defaults to app locale)
Topic::query()->whereHasLocale('name', 'sk');           // rows with a non-blank 'sk' value
Topic::query()->whereMissingLocale('name', 'sk');       // rows missing 'sk' (feed an AI-fill queue)
```

### Reading in another locale

Read a model in a locale other than the request locale without mutating global state — the app
locale is swapped for the closure and always restored afterwards (even on an exception):

```php
use RoundlyConsulting\Translatable\Facades\Translatable;

$sk = Translatable::usingLocale('sk', fn (): string => $topic->name); // 'Investovanie'
```

### Fallback modes

The `FallbackMode` enum drives resolution:

- `FallbackMode::None` — exact requested locale only.
- `FallbackMode::Fallback` — exact, then the configured fallback locale.
- `FallbackMode::Any` — exact, then fallback locale, then the first available value, walking the
  supported locales in order and then any other stored locale alphabetically. The pick never
  depends on how the database orders JSON keys, so a row renders the same language on every
  engine and before and after a reload.

> **Cross-locale disclosure with `Any` (the shipped default).** When both the requested and
> fallback locales are empty, `Any` renders the **first available** locale's value — so content you
> deliberately left untranslated for a locale can still appear in another language. If some content
> is legally or compliance gated per locale, switch to `Fallback` (or `None`) globally via
> `TRANSLATABLE_FALLBACK`/config, or per model with `$translatableFallbackMode`. `Any` is kept as
> the default so content never renders blank; the tradeoff is this leak.

Override per model:

```php
use RoundlyConsulting\Translatable\Enums\FallbackMode;

protected ?FallbackMode $translatableFallbackMode = FallbackMode::Fallback;
protected ?string $translatableFallbackLocale = 'en';
```

### Migrations

```php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('topics', function (Blueprint $table): void {
    $table->id();
    $table->translatable('name');          // jsonb column
    $table->translatable('description')->nullable();
    $table->localizedSlug('slug');         // sluggable's macro — jsonb / json / text per engine
    $table->timestamps();
});
```

### Slugs

Slugs are owned by [`sluggable-for-laravel`](https://github.com/roundly-consulting/sluggable-for-laravel)
(installed with this package). A translatable model gets per-locale slugs with two traits and two
interfaces — no cast, no glue:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Sluggable\Concerns\HasSlug;
use RoundlyConsulting\Sluggable\Contracts\Sluggable;
use RoundlyConsulting\Sluggable\Definitions\SlugDefinition;
use RoundlyConsulting\Sluggable\Definitions\SlugOptions;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

final class Topic extends Model implements Translatable, Sluggable
{
    use HasTranslations;
    use HasSlug;

    /** @var list<string> */
    public array $translatable = ['name', 'description', 'slug'];

    public function slugOptions(): SlugOptions
    {
        return SlugOptions::make(
            SlugDefinition::for('slug')->from('name')->routeKey(),
        );
    }

    /** @return list<string> */
    protected function translationStatusExcludes(): array
    {
        return ['slug'];
    }
}

$topic = Topic::create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);
$topic->getTranslations('slug');   // ['en' => 'investing', 'sk' => 'investovanie']
```

How it fits together:

- `Translatable` extends sluggable's `ProvidesLocaleMaps`; `HasTranslations` implements it
  (`isLocaleMapAttribute()` → `isTranslatableAttribute()`, `getLocaleMap()` → `getTranslations()`,
  `setLocaleMap()` → `setTranslations()`). Sluggable therefore detects the `slug` attribute as a
  locale map, builds the `sk` slug from the `sk` name regardless of the request locale, and every
  slug it writes goes through translatable's locale-key validation and `strict_locales`.
- **The model must `implements Translatable`.** A model that uses `HasTranslations` without the
  interface has no cast on its `slug`, so sluggable refuses it with
  `InvalidSlugDefinitionException` (`localeMapContractMissing`) rather than treating the jsonb
  column as a plain string.
- Uniqueness, engine-native unique indexes (`SlugIndexes`, `php artisan sluggable:indexes`),
  querying (`whereSlug`, `whereSlugInAnyLocale`, `findBySlug`), route binding, the `UniqueSlug`
  validation rule, slug history and redirects are all sluggable's — see its README.

### Admin validation

```php
use RoundlyConsulting\Sluggable\Rules\UniqueSlug;
use RoundlyConsulting\Translatable\Facades\Translatable;

public function rules(): array
{
    // Extend the slug rules rather than re-declaring `slug`: a second `'slug' => […]` key would
    // silently replace `sometimes|array` and let a non-map value through.
    $slug = Translatable::rules('slug', required: false);
    $slug['slug'][] = UniqueSlug::for(Topic::class)->ignore($this->route('topic'));   // sluggable's rule

    return [...Translatable::rules('name', required: true), ...$slug];
}
```

Cap length or add custom per-locale value rules via the `each` parameter (applied to every
locale of the field):

```php
...Translatable::rules('name', required: true, each: ['max:120']);
```

Building your own rule list? Take only the "at least one locale is filled" rule:

```php
'name' => ['required', 'array', ...Translatable::filledRule('name')],
```

Other admin helpers:

- `Translatable::fromInput($input)` — normalise input into a locale map (a bare string becomes
  the current locale; blank and unsupported locales are dropped, a bare string's locale
  included). Ideal for DTO mapping.
- `Translatable::apply($model, new TranslationChanges([...]))` — PATCH-merge: only supplied
  locales are touched.
- `Translatable::search($query, new TranslationSearch(fields: ['name'], term: 'invest'))` —
  per-locale, case-insensitive search across the supported locales (`ilike` on PostgreSQL, `like`
  everywhere else). A search with no fields (or no supported locale) searches nothing and
  matches no rows.

  The term is treated as a **literal substring**: `%`, `_` and `\` are escaped, and the SQL states
  its escape character explicitly, so a search for `100%` or `a_b` finds exactly those rows on
  every driver — including SQLite and SQL Server, which have no default `LIKE` escape character.
  The needle is always bound; only the searched field name (validated as a plain identifier)
  reaches the SQL.

### Missing-locale badge (Blade)

Drop the "which locales are still missing" badge every admin form repeats into any Blade view:

```blade
<x-translatable-status :model="$topic" />
```

It renders each field's missing locales for a partial model, or a "complete" badge when the model
is fully translated (respecting `translationStatusExcludes()`).

### Storage format

Values are stored as a plain `{ "en": "…", "sk": "…" }` JSON object — no vendor wrapper. Rows
written by the hand-rolled "json cast + locale accessor" pattern read back identically, so
there is **no data migration** when adopting this package.

### `php artisan about`

The package contributes a `Translatable` section reporting its shape — how many locales are
configured and where they come from, the fallback mode, whether the fallback locale is the app's
(`DEFAULT` — equal to `app.fallback_locale`), overridden (`SET`) or empty (`NONE`), and the
strict-locale switch (`ON`/`OFF`):

```bash
php artisan about --only=translatable
```

It reports **counts and switches, never values**: your locale list is never printed. (Slug
settings appear in sluggable's own `about` section.)

## Testing

```bash
composer test
```

The suite runs on SQLite by default. Point it at a real engine with the standard
`TESTING_DB_*` vars — `TESTING_DB_DRIVER` is the one that actually moves it:

```bash
TESTING_DB_DRIVER=pgsql TESTING_DB_HOST=127.0.0.1 TESTING_DB_PORT=5432 \
  TESTING_DB_DATABASE=testing TESTING_DB_USERNAME=testing TESTING_DB_PASSWORD=secret \
  vendor/bin/pest
```

CI runs all three engines, and each one earns its place:

| Leg | What only it can catch |
|---|---|
| **sqlite** | a `LIKE` with no `ESCAPE` clause. SQLite has no default LIKE escape character, so an unescaped term matches **nothing** — while the same code is green on PostgreSQL and MySQL. This package shipped exactly that bug. |
| **pgsql** | the PostgreSQL-only surface: the `ilike` path and real `jsonb` columns — including sluggable's per-locale slugs over them. |
| **mysql** | the `like` branch on a real server. Everything that is not PostgreSQL takes that branch, so without this leg "not PostgreSQL" was only ever proven against SQLite — the one engine that disagrees with MySQL about escaping. |

The PostgreSQL-only cases skip visibly when no engine is reachable, so a run that asserts
nothing cannot report green.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=translatable-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=translatable-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Copyright (c) roundly-consulting. See [LICENSE.md](LICENSE.md).
