# Changelog

All notable changes to `translatable-for-laravel` will be documented in this file.

## Unreleased

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
- Slug uniqueness now supports non-`id` primary keys via a `keyName` on `UniqueTranslatedSlug`,
  `UniqueSlugContext`, and `TranslatableSlug::slugTaken()`.
- `Translations::rules()` accepts an `each` parameter for extra per-locale value rules.
- Added the `<x-translatable-status>` Blade component for the missing-locale badge.
- Tightened `getTranslations()` return typing with a conditional return type.

## 1.0.0

- Initial release.
