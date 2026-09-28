<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Exceptions;

/**
 * Thrown when a locale key on a write path is malformed or (in strict mode) not supported.
 * Extends the package base so callers can catch every translatable error consistently.
 */
final class InvalidLocaleException extends TranslatableException
{
    public static function forFormat(string $locale): self
    {
        return new self(
            "Locale [{$locale}] is not a valid locale key. ".
            'Expected a BCP-47-like code such as "en", "en_US" or "pt-BR". '.
            'Route raw request maps through Translatable::fromInput() to drop bad keys.'
        );
    }

    public static function notSupported(string $locale): self
    {
        return new self(
            "Locale [{$locale}] is not in the supported locales list and ".
            'translatable.strict_locales is enabled.'
        );
    }
}
