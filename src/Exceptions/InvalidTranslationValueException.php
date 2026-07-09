<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Exceptions;

/**
 * Thrown when a translation value is not a scalar the locale map can represent
 * (arrays/objects/booleans). Locale maps store `array<string, string>` only.
 */
final class InvalidTranslationValueException extends TranslatableException
{
    public static function make(string $locale, string $type): self
    {
        return new self(
            "Translation value for locale [{$locale}] must be a string, int or float; got [{$type}]. ".
            'A locale map stores scalar strings only.'
        );
    }
}
