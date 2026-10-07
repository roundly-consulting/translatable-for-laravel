<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\DataTransferObjects;

/**
 * A set of translation changes for `Translatable::apply()`: field => locale => value.
 * apply() merges them into the model in memory — the supplied locales are set, the model's
 * other locales are kept — and the model's `save()` writes the whole column (last write wins).
 */
final readonly class TranslationChanges
{
    /**
     * @param  array<string, array<string, string>>  $fields
     */
    public function __construct(
        public array $fields,
    ) {}

    /**
     * @param  array<string, array<string, string>>  $fields
     */
    public static function make(array $fields): self
    {
        return new self($fields);
    }
}
