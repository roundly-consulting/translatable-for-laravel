# Changelog

All notable changes to `translatable-for-laravel` will be documented in this file.

## Unreleased

- **Removed: the slug feature** — it moved to `sluggable-for-laravel`, now a hard dependency.
  Gone: `HasTranslatableSlug`, `SlugGenerator`, `TranslatableSlug`, `UniqueTranslatedSlug`,
  `translatable:slug-indexes`, the `SlugOptions`/`UniqueSlugContext` DTOs, the
  `translatableSlug` Blueprint macro, the `translatable.slug.*` config, the `unique_slug` lang line
  and the slug `about` lines. See the README's "Migrating from `HasTranslatableSlug`".
- The `Translatable` contract now extends sluggable's `ProvidesLocaleMaps`; `HasTranslations`
  implements `isLocaleMapAttribute()`, `getLocaleMap()` and `setLocaleMap()`.
- Sluggable's `SlugLocales` is bound to the new `TranslatableSlugLocales` adapter (one locale
  source of truth for translations and slugs).
- Localized array/JSON serialization: `toArray()`/`toJson()`/API Resources now emit the resolved
  locale value for translatable attributes (matching property access), not the raw JSON map.
  Output shape only — persisted data is unchanged.
- Added the `Translatable` facade over a bound, swappable `TranslationManager`; the static
  `Translations::…` helpers delegate to it.
- Added whole-model status helpers: `missingTranslations()`, `isFullyTranslated()`,
  `translationCompleteness()`, plus a `translationStatusExcludes()` hook.
- Added the opt-in `DispatchesTranslationEvents` trait and `TranslationsChanged` event.
- Added driver-agnostic query scopes `whereLocale()`, `whereHasLocale()`, `whereMissingLocale()`.
- Added `Translatable::usingLocale()` for stateless, scoped locale reads.
- `Translations::rules()` accepts an `each` parameter for extra per-locale value rules.
- Added the `<x-translatable-status>` Blade component for the missing-locale badge.
- Tightened `getTranslations()` return typing with a conditional return type.

## 1.0.0

- Initial release.
