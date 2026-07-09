<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RoundlyConsulting\Translatable\Support\TranslatableSlug;
use RoundlyConsulting\Translatable\Support\Translations;

/**
 * A reusable rule asserting per-locale slug uniqueness for a locale-map field.
 */
final readonly class UniqueTranslatedSlug implements ValidationRule
{
    public function __construct(
        private string $table,
        private ?int $ignoreId = null,
        private string $column = 'slug',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (Translations::fromInput($value) as $locale => $slug) {
            if (TranslatableSlug::slugTaken($this->table, $this->column, $locale, $slug, $this->ignoreId)) {
                $fail((string) __('translatable::validation.unique_slug', ['locale' => $locale]));

                return;
            }
        }
    }
}
