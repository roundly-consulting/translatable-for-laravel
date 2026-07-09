<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\DataTransferObjects;

/**
 * Everything needed to assert per-locale slug uniqueness for an admin request.
 */
final readonly class UniqueSlugContext
{
    public function __construct(
        public mixed $input,
        public string $table,
        public int|string|null $ignoreId = null,
        public string $column = 'slug',
        public string $keyName = 'id',
    ) {}
}
