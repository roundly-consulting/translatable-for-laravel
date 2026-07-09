<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Exceptions;

final class NotATranslatableAttributeException extends TranslatableException
{
    public static function make(string $model, string $attribute): self
    {
        return new self(
            "Attribute [{$attribute}] is not declared translatable on [{$model}]. ".
            'Add it to the model\'s $translatable list.'
        );
    }
}
