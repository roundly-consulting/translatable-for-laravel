<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Exceptions;

/**
 * Thrown when one locale of a translatable attribute is written on a model that was loaded
 * without that column (`select('id')`). The write merges into the stored map, and the stored
 * map is unknown, so saving would replace every other stored locale with the one written.
 */
final class TranslationsNotLoadedException extends TranslatableException
{
    public static function make(string $model, string $attribute): self
    {
        return new self(
            "Cannot change one locale of [{$attribute}] on [{$model}]: the model was loaded without ".
            'that column, so its other stored locales are unknown and saving would erase them. '.
            'Select the column, or replace the whole map with setTranslations().'
        );
    }
}
