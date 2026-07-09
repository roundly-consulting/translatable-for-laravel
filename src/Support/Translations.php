<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Translatable\Contracts\Translatable;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;

/**
 * The reusable admin-input toolkit for locale-map fields. Each helper delegates to the bound
 * TranslationManager so a swapped/mocked manager is observed everywhere (facade + trait + here).
 */
final class Translations
{
    private static function manager(): TranslationManager
    {
        return app(TranslationManager::class);
    }

    /**
     * The supported locales, from the bound SupportedLocales source of truth.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        return self::manager()->supported();
    }

    public static function currentLocale(): string
    {
        return self::manager()->currentLocale();
    }

    /**
     * Normalise arbitrary input into a locale map:
     * a bare string becomes the current locale; blank + unsupported locales are dropped.
     *
     * @return array<string, string>
     */
    public static function fromInput(mixed $input): array
    {
        return self::manager()->fromInput($input);
    }

    /**
     * Validation rules for a locale-map field: the field itself plus each per-locale value.
     * When required, at least one locale must be filled. Extra `$each` rules (e.g. `max:120`)
     * are appended to every per-locale value.
     *
     * @param  array<int, mixed>  $each
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $field, bool $required, array $each = []): array
    {
        return self::manager()->rules($field, $required, $each);
    }

    /**
     * A rule set enforcing that at least one locale of the field is non-blank.
     *
     * @return array<int, Closure(string, mixed, Closure): void>
     */
    public static function filledRule(string $field): array
    {
        return [
            static function (string $attribute, mixed $value, Closure $fail): void {
                $filled = is_array($value)
                    ? array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== '')
                    : [];

                if ($filled === []) {
                    $fail((string) __('translatable::validation.filled'));
                }
            },
        ];
    }

    /**
     * PATCH-merge a set of changes onto a model: only supplied locales are touched.
     */
    public static function apply(Translatable $model, TranslationChanges $changes): void
    {
        self::manager()->apply($model, $changes);
    }

    /**
     * Add a per-locale, case-insensitive search across the given fields.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public static function whereLike(Builder $query, TranslationSearch $search): Builder
    {
        return self::manager()->search($query, $search);
    }
}
