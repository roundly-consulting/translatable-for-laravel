<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\Enums\FallbackMode;

return [
    // Locale used when a requested locale has no value (FallbackMode::Fallback / Any, step 2).
    'fallback_locale' => env('TRANSLATABLE_FALLBACK_LOCALE', config('app.fallback_locale', 'en')),

    // How far the fallback chain reaches (R4). None = exact only; Fallback = exact -> fallback
    // locale; Any = exact -> fallback locale -> first available (content never renders blank).
    'fallback' => FallbackMode::tryFrom((string) env('TRANSLATABLE_FALLBACK', 'any')) ?? FallbackMode::Any,

    // Default supported locales. Hosts SHOULD rebind SupportedLocales to their own source
    // (e.g. Locale::SUPPORTED) so there is one source of truth (R3).
    'locales' => ['en', 'sk'],

    'slug' => [
        'source_field' => 'name',   // default column slugs are generated from
        'separator' => '-',
        'max_words' => 12,          // cap Str::slug input length
        'reserved' => [],           // slugs that may never be generated (e.g. 'edit', 'create')
    ],
];
