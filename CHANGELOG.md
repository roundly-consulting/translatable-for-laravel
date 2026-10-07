# Changelog

All notable changes to `translatable-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.1.0 - 2026-10-07

### Changed

- **Behaviour change:** writing one locale — `setTranslation()`, `forgetTranslation()`,
  `Translatable::apply()`, `$model->name = '…'` or a `name->sk` key in `fill()` / `update()` — on
  a model loaded without that column (for example `Topic::query()->select('id')->first()`) now
  throws `TranslationsNotLoadedException`. It used to read the missing column as an empty map, so
  the next `save()` wrote that one locale over the column and silently erased every other stored
  locale. Replacing the whole map (`setTranslations()`, `$model->name = [...]`) still works, and
  so do new and just-created models.
- With `Model::preventAccessingMissingAttributes()` on, the translation reads
  (`getTranslation()`, `getTranslations()`, `hasTranslation()`, `missingLocales()`,
  `isFullyTranslated()` and the other status helpers) on such a model now throw
  `MissingAttributeException`, as `$model->name` already did. With it off, they still read the
  column as empty.
- Documentation: the README hero image uses an absolute URL, so it renders on Packagist and other
  sites.
- Documentation: the README's example model now declares `protected $fillable = ['name'];`, so its
  `Topic::create(['name' => …])` runs instead of throwing `MassAssignmentException`.
- Documentation: the README's `fromInput()` line now says what it does — it replaces the whole
  locale map with the form's locales, which is how a full form clears a locale. The docs add a
  partial-update recipe (`Translatable::apply()`) that keeps the locales a request leaves out.
- Documentation: `Translatable::apply()` and `TranslationChanges` are no longer described as a
  per-locale PATCH. They merge in memory, and `save()` writes the whole column, so two requests
  saving different locales of the same row are last-write-wins; the docs add a transaction +
  `lockForUpdate()` recipe for concurrent editors.
- Documentation: the README states the supported databases — PostgreSQL 12+ and MySQL 8.0.23+
  (SQLite for tests) — instead of "any Laravel-supported database". On SQLite, case-insensitive
  `Translatable::search()` folds ASCII letters only.

### Fixed

- Locale keys and search fields with a trailing newline (`"en\n"`, `"name\n"`) are now rejected
  like any other malformed key. They used to pass the format check, so a write stored a junk
  locale key, the scopes ran with it, and `Translatable::search()` reached the database and
  failed there instead of throwing `TranslatableException`.
- `whereLocale()`, `whereHasLocale()`, `whereMissingLocale()` and `Translatable::search()` now
  qualify the column with the model's table (`topics.name->en`), so they work on a query that
  joins another table with a column of the same name instead of failing as ambiguous.
- The `Locales` row of `php artisan about` now counts the bound `SupportedLocales` source (the
  class named on the next row) instead of the `translatable.locales` config list, so a host that
  rebinds the source sees its real locale count.
- A per-model `$translatableFallbackMode` declared as a string (`'none'`, `'fallback'`, `'any'`)
  is now honoured; it used to be ignored, so `'none'` silently fell back to the global mode
  (`Any` by default) and could show another locale's content. A value that names no mode now
  throws `InvalidConfigurationException`, like the `translatable.fallback` setting does.
- A typed `$translatableFallbackMode` / `$translatableFallbackLocale` declared without a default
  now means "use the config" instead of throwing an uninitialised-property `Error` on every read.
- A per-model `$translatableFallbackLocale` is now validated like the config setting: a malformed
  key throws `InvalidLocaleException` (blank still means no fallback locale).

## 1.0.1 - 2026-10-04

### Changed

- Maintenance: `composer.json` `homepage` and `support.docs` now point to the documentation site.

### Fixed

- Slovak (`sk`) translations now ship alongside English for every language file.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Translatable Eloquent attributes stored as a plain `{ "en": "…", "sk": "…" }` map in a
  `json` / `jsonb` column, via the `HasTranslations` trait and `Translatable` contract.
- Reads that return the current locale through a configurable fallback chain (`FallbackMode`:
  `None`, `Fallback`, `Any`), set globally or per model.
- Locale keys and values validated on every write, with optional strict locales, and
  `Translatable::fromInput()` to clean untrusted request input.
- Serialization (`toArray()`, JSON, API Resources) that emits the resolved locale value.
- Translation-status helpers — `missingLocales()`, `missingTranslations()`, `isFullyTranslated()`,
  `translationCompleteness()` — and an `<x-translatable-status>` Blade badge.
- Driver-agnostic query scopes (`whereLocale()`, `whereHasLocale()`, `whereMissingLocale()`) and
  literal-safe per-locale search with `Translatable::search()`.
- A `translatable()` schema macro for migrations.
- Per-locale slugs through `sluggable-for-laravel`, using one `SupportedLocales` source of truth.
- A `Translatable` facade over the injectable `TranslationManager` — the package's one front
  door. Locales: `supported()`, `isSupported()`, `currentLocale()`, `ensureLocale($locale,
  strict:)`. Fallback chain: `fallbackMode()`, `fallbackLocale()`, `resolve($map, ?$locale,
  ?$mode, ?$fallbackLocale)` for a raw locale map. Admin input: `fromInput()`, `rules()`,
  `filledRule()`, `apply()`. Plus `search()` and `usingLocale()` to read a model in another
  locale. The `HasTranslations` trait reads its locale set, fallback settings, locale
  validation and map resolution through the same manager, so a swapped manager is observed on
  every model. No fake: nothing on the facade has a side effect to record.
- Admin validation rules (`Translatable::rules()`, `Translatable::filledRule()`) and PATCH-style
  merging with `Translatable::apply()`.
- An opt-in `TranslationsChanged` event and a `Translatable` section in `php artisan about`.
