<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use RoundlyConsulting\Sluggable\Contracts\SlugLocales;

/**
 * Hands sluggable translatable's locale set, so slugs and translations share one source of
 * truth: the bound SupportedLocales, `translatable.fallback_locale` and the app locale.
 * Resolves the manager on every call — a host that rebinds SupportedLocales or swaps the
 * manager after this adapter was built still wins.
 */
final class TranslatableSlugLocales implements SlugLocales
{
    public function supported(): array
    {
        return app(TranslationManager::class)->supported();
    }

    public function fallback(): string
    {
        return app(TranslationManager::class)->fallbackLocale();
    }

    public function current(): string
    {
        return app(TranslationManager::class)->currentLocale();
    }
}
