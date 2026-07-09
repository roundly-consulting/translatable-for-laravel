<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Contracts\Translatable;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;

/**
 * The reusable admin-input toolkit for locale-map fields.
 */
final class Translations
{
    /**
     * The supported locales, from the bound SupportedLocales source of truth.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        return app(SupportedLocales::class)->supported();
    }

    public static function currentLocale(): string
    {
        return app()->getLocale();
    }

    /**
     * Normalise arbitrary input into a locale map:
     * a bare string becomes the current locale; blank + unsupported locales are dropped.
     *
     * @return array<string, string>
     */
    public static function fromInput(mixed $input): array
    {
        if (is_string($input)) {
            return $input === '' ? [] : [self::currentLocale() => $input];
        }

        if (! is_array($input)) {
            return [];
        }

        $supported = self::supported();
        $map = [];

        foreach ($input as $locale => $value) {
            $locale = (string) $locale;

            if (! in_array($locale, $supported, true)) {
                continue;
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            $map[$locale] = $value;
        }

        return $map;
    }

    /**
     * Validation rules for a locale-map field: the field itself plus each per-locale value.
     * When required, at least one locale must be filled.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $field, bool $required): array
    {
        $rules = [
            $field => $required
                ? array_merge(['required', 'array'], self::filledRule($field))
                : ['sometimes', 'array'],
        ];

        foreach (self::supported() as $locale) {
            $rules["{$field}.{$locale}"] = ['nullable', 'string'];
        }

        return $rules;
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
        foreach ($changes->fields as $field => $localeMap) {
            foreach ($localeMap as $locale => $value) {
                $model->setTranslation($field, (string) $locale, $value);
            }
        }
    }

    /**
     * Add a per-locale, case-insensitive search across the given fields.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public static function whereLike(Builder $query, TranslationSearch $search): Builder
    {
        $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $term = '%'.$search->term.'%';
        $locales = self::supported();

        return $query->where(static function (Builder $inner) use ($search, $operator, $term, $locales): void {
            foreach ($search->fields as $field) {
                foreach ($locales as $locale) {
                    $inner->orWhere("{$field}->{$locale}", $operator, $term);
                }
            }
        });
    }
}
