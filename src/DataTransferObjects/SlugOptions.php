<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\DataTransferObjects;

/**
 * Slug-generation options, built from config and overridable per model.
 */
final readonly class SlugOptions
{
    /**
     * @param  list<string>  $reserved
     */
    public function __construct(
        public string $sourceField,
        public string $separator,
        public int $maxWords,
        public array $reserved,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array<string, mixed> $slug */
        $slug = config('translatable.slug', []);

        /** @var list<string> $reserved */
        $reserved = array_values(array_map(
            static fn (mixed $value): string => (string) $value,
            is_array($slug['reserved'] ?? null) ? $slug['reserved'] : [],
        ));

        return new self(
            sourceField: (string) ($slug['source_field'] ?? 'name'),
            separator: (string) ($slug['separator'] ?? '-'),
            maxWords: (int) ($slug['max_words'] ?? 12),
            reserved: $reserved,
        );
    }
}
