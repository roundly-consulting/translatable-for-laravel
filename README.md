<p align="center">
  <a href="https://roundly-consulting.com/open-source">
    <img src="art/hero.png" alt="Translatable For Laravel — Roundly open source" width="100%">
  </a>
</p>

# translatable-for-laravel

Locale-map (`jsonb`) translatable attributes, a fallback chain, and per-locale unique
translatable slugs for Eloquent — native, with zero third-party dependencies.

A single `json`/`jsonb` column stores a plain `{ "en": "…", "sk": "…" }` map per attribute.
Reads return the current locale through a configurable fallback chain so content never renders
blank; slugs are generated per locale and can be made unique per locale on PostgreSQL. This is
the org-wide native solution for multi-locale Eloquent attributes.

## Requirements

- PHP 8.4+
- Laravel 12 or 13
- `roundly-consulting/enums-for-laravel` (installed automatically) — backs the `FallbackMode` enum
- PostgreSQL is required only for **per-locale slug uniqueness** (functional unique indexes).
  Everything else works on any Laravel-supported database; the index helpers are a no-op off
  PostgreSQL.

## Installation

```bash
composer require roundly-consulting/translatable-for-laravel
```

Optionally publish the config and the validation language lines:

```bash
php artisan vendor:publish --tag="translatable-config"
php artisan vendor:publish --tag="translatable-translations"
```

There is **no** migrations tag — you write your own tables using the column/index helpers
below.

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
    'fallback' => FallbackMode::tryFrom((string) env('TRANSLATABLE_FALLBACK', 'any')) ?? FallbackMode::Any,

    // Reject any locale key not in the supported list on writes (malformed keys are always
    // rejected regardless). Off by default, so any well-formed locale is accepted.
    'strict_locales' => (bool) env('TRANSLATABLE_STRICT_LOCALES', false),

    // Default supported locales. Hosts SHOULD rebind SupportedLocales to their own source.
    'locales' => ['en', 'sk'],

    'slug' => [
        'source_field' => 'name',   // default column slugs are generated from
        'separator'    => '-',
        'max_words'    => 12,        // cap the slug input length
        'reserved'     => [],        // slugs that may never be generated (e.g. 'edit')
    ],
];
```

| Key | Type | Default | Env | Purpose |
|-----|------|---------|-----|---------|
| `fallback_locale` | `string` | `app.fallback_locale` / `en` | `TRANSLATABLE_FALLBACK_LOCALE` | Locale tried after the exact one. |
| `fallback` | `FallbackMode` | `FallbackMode::Any` | `TRANSLATABLE_FALLBACK` (`none`/`fallback`/`any`) | How far the fallback chain reaches. |
| `strict_locales` | `bool` | `false` | `TRANSLATABLE_STRICT_LOCALES` | Reject writes for locales outside the supported list. |
| `locales` | `list<string>` | `['en', 'sk']` | — | Default supported locales. |
| `slug.source_field` | `string` | `name` | — | Column slugs are generated from. |
| `slug.separator` | `string` | `-` | — | Slug word separator. |
| `slug.max_words` | `int` | `12` | — | Caps the slug input length. |
| `slug.reserved` | `list<string>` | `[]` | — | Slugs that are never generated. |

The package works with **zero** host configuration.

### One source of truth for locales

Bind the `SupportedLocales` contract in your app's service provider to wrap a single source of
truth (used by `Translations`, `missingLocales`, `fromInput`, and the slug index helpers):

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

## Usage

### Translatable attributes

List the translatable attributes in a `public array $translatable` property and implement the
`Translatable` contract (the trait provides every method). The model needs **no** `array` cast —
the trait owns JSON serialization for its listed attributes.

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Concerns\HasTranslatableSlug;
use RoundlyConsulting\Translatable\Contracts\Translatable;

final class Topic extends Model implements Translatable
{
    use HasTranslations;
    use HasTranslatableSlug;

    /** @var list<string> */
    public array $translatable = ['name', 'description', 'slug'];
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
```

Blank/`null` values are dropped, so a map never stores an empty string and the fallback chain
always has something real to fall back to.

### Locale keys are validated on write

Every write path (`$model->name = [...]`, `setTranslation`, `setTranslations`, mass assignment)
validates its locale **keys**. Malformed keys — anything with quotes, spaces, markup or SQL, e.g.
a mass-assigned `name[<script>]=…` — are rejected with an `InvalidLocaleException`; values must be
scalars (strings, or ints/floats cast to string), so a nested-array payload raises an
`InvalidTranslationValueException` instead of being stored. Turn on `strict_locales` to also reject
well-formed keys that aren't in your supported list.

**Route raw request maps through `Translations::fromInput()`** — it drops unsupported and blank
locales *before* they reach the model, so untrusted `$request->input('name')` never triggers a
write-path exception:

```php
$topic->setTranslations('name', Translations::fromInput($request->input('name')));
// or validate first with Translations::rules('name', required: true)
```

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

### The `Translatable` facade

The reusable toolkit is discoverable, injectable, and swappable in host tests via the `Translatable`
facade over a bound `TranslationManager` (the static `Translations::…` helpers delegate to the same
manager, so a swapped fake is observed everywhere):

```php
use RoundlyConsulting\Translatable\Facades\Translatable;

$locales = Translatable::supported();                 // list<string>
$map     = Translatable::fromInput($request->input('name'));
$current = Translatable::currentLocale();
$mode    = Translatable::fallbackMode();               // FallbackMode
$fb      = Translatable::fallbackLocale();             // string

// In a test:
Translatable::swap($fakeManager);
```

### Fallback modes

The `FallbackMode` enum drives resolution:

- `FallbackMode::None` — exact requested locale only.
- `FallbackMode::Fallback` — exact, then the configured fallback locale.
- `FallbackMode::Any` — exact, then fallback locale, then the first available value.

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
use RoundlyConsulting\Translatable\Support\TranslatableSlug;

Schema::create('topics', function (Blueprint $table): void {
    $table->id();
    $table->translatable('name');          // jsonb column (macro over the helper)
    $table->translatable('description')->nullable();
    $table->translatableSlug();            // jsonb 'slug' column
    $table->timestamps();
    $table->softDeletes();
});

// After the table exists — one functional unique index per supported locale (PostgreSQL only):
TranslatableSlug::uniqueIndexes('topics');
```

`TranslatableSlug::column($table, 'slug')` and `TranslatableSlug::uniqueIndexes($table, 'slug')`
are the plain helpers behind the `translatableSlug()` / `translatable()` macros. `uniqueIndexes`
is a no-op on non-PostgreSQL drivers, and a `NULL` (missing) locale is exempt so partial
translations stay legal.

> **Uniqueness is authoritative on PostgreSQL only.** The functional unique indexes are the real
> guard. On other drivers there is no per-locale unique index, so uniqueness is **best-effort**:
> generation avoids collisions at create time, and a create that loses a race is retried with the
> next suffix, but two truly-concurrent writers can still mint the same slug. Use PostgreSQL where
> per-locale slug uniqueness must be guaranteed. (Table/column names passed to `uniqueIndexes` and
> the `translatable:slug-indexes` command are validated as plain identifiers before any DDL runs.)

### Translatable slugs

`HasTranslatableSlug` generates a slug per locale on create from `slug.source_field`:

```php
$topic = Topic::create(['name' => ['en' => 'Investing', 'sk' => 'Investovanie']]);
$topic->getTranslations('slug'); // ['en' => 'investing', 'sk' => 'investovanie']
```

- Admin-supplied slugs are preserved; only missing locales are generated.
- Per-locale collisions suffix: `investing`, `investing-2`, `investing-3`, …
- A locale with no usable source gets a short random slug.
- A model without a translatable `slug` column falls back to a single plain-string slug (same
  trait).

Resolve by slug:

```php
Topic::query()->whereLocaleSlug($slug)->first();  // request locale -> fallback-locale slug
Topic::query()->whereAnySlug($slug)->first();      // any supported locale (stale-locale rescue)

// Route model binding: current-locale slug -> fallback slug -> any-locale slug
Route::get('/topics/{topic:slug}', fn (Topic $topic) => $topic);
```

The scopes validate a request-supplied `$locale` and reject a non-translatable `$field`, so they
are safe to hand `?locale=` straight from the request.

By default route binding resolves **by slug only** — a numeric slug like `"2024"` is never shadowed
by the record with id `2024`, and slug routes can't be enumerated by id. Opt into an id fallback
(tried only *after* the slug misses) per model:

```php
// Resolve {topic:slug} by primary key when no slug matches.
protected bool $resolveSlugBindingById = true;
```

### Admin validation

```php
use RoundlyConsulting\Translatable\Support\Translations;
use RoundlyConsulting\Translatable\Support\TranslatableSlug;
use RoundlyConsulting\Translatable\DataTransferObjects\UniqueSlugContext;
use RoundlyConsulting\Translatable\Rules\UniqueTranslatedSlug;

public function rules(): array
{
    return [
        ...Translations::rules('name', required: true),
        ...Translations::rules('slug', required: false),
        'slug' => new UniqueTranslatedSlug('topics', ignoreId: $this->route('topic')?->id),
    ];
}

// Or the after-validation style:
public function withValidator(Validator $validator): void
{
    $validator->after(fn () => TranslatableSlug::assertUnique($validator, new UniqueSlugContext(
        input: $this->input('slug'),
        table: 'topics',
        ignoreId: $this->route('topic')?->id,
    )));
}
```

Cap length or add custom per-locale value rules via the `each` parameter (applied to every
locale of the field):

```php
...Translations::rules('name', required: true, each: ['max:120']);
```

For a model with a non-`id` primary key (uuid, custom, composite key column), pass its key name so
uniqueness ignores the right row:

```php
new UniqueTranslatedSlug('topics', ignoreId: $record->getKey(), keyName: 'uuid');

new UniqueSlugContext(input: $this->input('slug'), table: 'topics', ignoreId: $id, keyName: 'uuid');
```

Other `Translations` helpers:

- `Translations::fromInput($input)` — normalise input into a locale map (a bare string becomes
  the current locale; blank and unsupported locales are dropped). Ideal for DTO mapping.
- `Translations::apply($model, new TranslationChanges([...]))` — PATCH-merge: only supplied
  locales are touched.
- `Translations::whereLike($query, new TranslationSearch(fields: ['name'], term: 'invest'))` —
  per-locale, case-insensitive search.

### Missing-locale badge (Blade)

Drop the "which locales are still missing" badge every admin form repeats into any Blade view:

```blade
<x-translatable-status :model="$topic" />
```

It renders each field's missing locales for a partial model, or a "complete" badge when the model
is fully translated (respecting `translationStatusExcludes()`).

### Command

Run once after adding a supported locale to (re)create the missing per-locale unique indexes
(create-only; PostgreSQL only):

```bash
php artisan translatable:slug-indexes topics --column=slug
```

### Storage format

Values are stored as a plain `{ "en": "…", "sk": "…" }` JSON object — no vendor wrapper. Rows
written by the hand-rolled "json cast + locale accessor" pattern read back identically, so
there is **no data migration** when adopting this package.

## Testing

```bash
composer test
```

The PostgreSQL slug-uniqueness suite runs when `TRANSLATABLE_PGSQL_*` env vars point at a test
database (host, port, database, username, password); otherwise it skips with a note.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). Copyright (c) roundly-consulting. See [LICENSE.md](LICENSE.md).
