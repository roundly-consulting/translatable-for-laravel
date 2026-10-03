<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;

/**
 * Default SupportedLocales binding — reads config('translatable.locales') strictly: unset
 * means none, and anything other than a list of well-formed locale keys throws
 * {@see InvalidConfigurationException} naming the key (entries used to be `(string)` cast).
 */
final class ConfigSupportedLocales implements SupportedLocales
{
    public function supported(): array
    {
        $locales = config('translatable.locales') ?? [];

        if (! is_array($locales)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration value [translatable.locales] must be a list of locale keys, [%s] given.',
                is_scalar($locales) ? var_export($locales, true) : get_debug_type($locales),
            ));
        }

        $supported = [];

        foreach ($locales as $index => $locale) {
            if (! is_string($locale) || ! LocaleGuard::isValid($locale)) {
                throw new InvalidConfigurationException(sprintf(
                    'Configuration value [translatable.locales.%s] must be a locale key such as `en` or `pt-BR`, [%s] given.',
                    $index,
                    is_scalar($locale) ? var_export($locale, true) : get_debug_type($locale),
                ));
            }

            $supported[] = $locale;
        }

        return $supported;
    }
}
