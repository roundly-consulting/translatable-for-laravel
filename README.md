# translatable-for-laravel

Locale-map (`jsonb`) translatable attributes, a fallback chain, and per-locale unique
translatable slugs for Eloquent — native, with zero third-party dependencies.

A single `json`/`jsonb` column stores a plain `{ "en": "…", "sk": "…" }` map per attribute.
Reads return the current locale through a configurable fallback chain so content never renders
blank; slugs are generated per locale and can be made unique per locale on PostgreSQL. This is
the org-wide native replacement for `acme/laravel-translatable`.

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
    'fallback' => FallbackMode::tryFrom((string) env('TRANSLATABLE_FALLBACK', 'any')) ?? FallbackMode::Any,

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

### Fallback modes

The `FallbackMode` enum drives resolution:

- `FallbackMode::None` — exact requested locale only.
- `FallbackMode::Fallback` — exact, then the configured fallback locale.
- `FallbackMode::Any` — exact, then fallback locale, then the first available value.

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

// Route model binding: id -> current-locale slug -> fallback slug -> any-locale slug
Route::get('/topics/{topic:slug}', fn (Topic $topic) => $topic);
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

Other `Translations` helpers:

- `Translations::fromInput($input)` — normalise input into a locale map (a bare string becomes
  the current locale; blank and unsupported locales are dropped). Ideal for DTO mapping.
- `Translations::apply($model, new TranslationChanges([...]))` — PATCH-merge: only supplied
  locales are touched.
- `Translations::whereLike($query, new TranslationSearch(fields: ['name'], term: 'invest'))` —
  per-locale, case-insensitive search.

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
