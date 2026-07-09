<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use RoundlyConsulting\Translatable\Contracts\SupportedLocales;

/**
 * Default SupportedLocales binding — reads config('translatable.locales').
 */
final class ConfigSupportedLocales implements SupportedLocales
{
    public function supported(): array
    {
        /** @var array<array-key, mixed> $locales */
        $locales = config('translatable.locales', []);

        return array_values(array_map(
            static fn (mixed $locale): string => (string) $locale,
            $locales,
        ));
    }
}
