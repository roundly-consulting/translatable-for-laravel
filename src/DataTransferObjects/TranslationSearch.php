<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\DataTransferObjects;

/**
 * A per-locale search across one or more translatable fields.
 */
final readonly class TranslationSearch
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        public array $fields,
        public string $term,
    ) {}
}
