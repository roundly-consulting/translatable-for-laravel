<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\DataTransferObjects;

/**
 * A PATCH-style set of translation changes: field => locale => value.
 * Only the supplied locales are touched; untouched locales are preserved.
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
