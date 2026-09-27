# Changelog

All notable changes to `translatable-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Translatable Eloquent attributes stored as a plain `{ "en": "…", "sk": "…" }` map in a
  `json` / `jsonb` column, via the `HasTranslations` trait and `Translatable` contract.
- Reads that return the current locale through a configurable fallback chain (`FallbackMode`:
  `None`, `Fallback`, `Any`), set globally or per model.
- Locale keys and values validated on every write, with optional strict locales, and
  `Translations::fromInput()` to clean untrusted request input.
- Serialization (`toArray()`, JSON, API Resources) that emits the resolved locale value.
- Translation-status helpers — `missingLocales()`, `missingTranslations()`, `isFullyTranslated()`,
  `translationCompleteness()` — and an `<x-translatable-status>` Blade badge.
- Driver-agnostic query scopes (`whereLocale()`, `whereHasLocale()`, `whereMissingLocale()`) and
  literal-safe per-locale search with `Translations::whereLike()`.
- A `translatable()` schema macro for migrations.
- Per-locale slugs through `sluggable-for-laravel`, using one `SupportedLocales` source of truth.
- A `Translatable` facade, including `usingLocale()` to read a model in another locale.
- Admin validation rules (`Translations::rules()`) and PATCH-style merging with
  `Translations::apply()`.
- An opt-in `TranslationsChanged` event and a `Translatable` section in `php artisan about`.
