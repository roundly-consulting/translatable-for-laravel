<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\Enums\FallbackMode;

return [
    // Locale used when a requested locale has no value (FallbackMode::Fallback / Any, step 2).
    'fallback_locale' => env('TRANSLATABLE_FALLBACK_LOCALE', config('app.fallback_locale', 'en')),

    // How far the fallback chain reaches (R4). None = exact only; Fallback = exact -> fallback
    // locale; Any = exact -> fallback locale -> first available (content never renders blank).
    //
    // NOTE ON `Any` (the default): when a requested locale AND the fallback locale are both
    // empty, `Any` renders the FIRST available locale's value. That means content you have
    // deliberately left untranslated for a locale can still surface in another language — a
    // cross-locale disclosure. If some content is legally/compliance gated per locale, use
    // `Fallback` (or `None`) instead, globally here or per-model via $translatableFallbackMode.
    'fallback' => FallbackMode::tryFrom((string) env('TRANSLATABLE_FALLBACK', 'any')) ?? FallbackMode::Any,

    // When true, locale keys written to a model must be BOTH well-formed AND in the supported
    // locales list; otherwise a well-formed key of any locale is accepted. Malformed keys
    // (quotes, spaces, markup, SQL) are ALWAYS rejected regardless of this flag.
    // Env words are read as booleans (on/off, yes/no, true/false, 1/0).
    'strict_locales' => filter_var(env('TRANSLATABLE_STRICT_LOCALES', false), FILTER_VALIDATE_BOOL),

    // Default supported locales. Hosts SHOULD rebind SupportedLocales to their own source
    // (e.g. Locale::SUPPORTED) so there is one source of truth (R3).
    'locales' => ['en', 'sk'],

    // Slugs live in sluggable-for-laravel (config/sluggable.php); a model using HasTranslations
    // + HasSlug gets per-locale slugs from the locale set above.
];
