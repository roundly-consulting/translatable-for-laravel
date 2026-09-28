<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

/**
 * The one rule for what counts as a translation value on a read: a non-empty string, or an
 * int/float cast to one (the same values the write path accepts). Everything else — null, '',
 * booleans, arrays, objects — is no value, so it never wins a fallback walk.
 *
 * @internal building block — shared by the model's map reader and the fallback resolver.
 */
final class TranslationValue
{
    public static function readable(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
