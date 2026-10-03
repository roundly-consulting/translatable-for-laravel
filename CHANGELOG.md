# Changelog

All notable changes to `translatable-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

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
