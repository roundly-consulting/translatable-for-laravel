<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use RoundlyConsulting\Sluggable\Contracts\SlugLocales;

/**
 * Hands sluggable translatable's locale set, so slugs and translations share one source of
 * truth: the bound SupportedLocales, `translatable.fallback_locale` and the app locale.
 * Resolves the manager on every call — a host that rebinds SupportedLocales or swaps the
 * manager after this adapter was built still wins.
 *
 * Only well-formed locales cross over. The supported list feeds sluggable's per-locale index
 * DDL and JSON paths, so a malformed entry is refused here (as translatable's own slug index
 * helper did); an unset or blank fallback locale is `null` — sluggable's "no fallback" — rather
 * than an empty string its locale guard rejects.
 */
final class TranslatableSlugLocales implements SlugLocales
{
    public function supported(): array
    {
        return array_map(
            static fn (string $locale): string => LocaleGuard::ensure($locale),
            app(TranslationManager::class)->supported(),
        );
    }

    public function fallback(): ?string
    {
        $fallback = app(TranslationManager::class)->fallbackLocale();

        return LocaleGuard::isValid($fallback) ? $fallback : null;
    }

    public function current(): string
    {
        return app(TranslationManager::class)->currentLocale();
    }
}
