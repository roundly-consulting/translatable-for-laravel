<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Contracts\Translatable;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;
use RoundlyConsulting\Translatable\Enums\DatabaseDriver;
use RoundlyConsulting\Translatable\Enums\FallbackMode;

/**
 * The single, injectable/mockable front door for the locale-map toolkit. Bound in the
 * container and fronted by the `Translatable` facade; the static `Translations::…` helpers
 * delegate here so a host can swap one fake and have every call observe it.
 *
 * Intentionally NOT `final`: this is the package's one designed extension/swap seam. Hosts
 * override a method (e.g. `supported()`) by extending it and `Translatable::swap()`-ing the
 * subclass — the sibling methods observe the override polymorphically. See FacadeTest.
 */
class TranslationManager
{
    /**
     * The supported locales, from the bound SupportedLocales source of truth.
     *
     * @return list<string>
     */
    public function supported(): array
    {
        return app(SupportedLocales::class)->supported();
    }

    public function currentLocale(): string
    {
        return app()->getLocale();
    }

    /**
     * Normalise arbitrary input into a locale map:
     * a bare string becomes the current locale; blank + unsupported locales are dropped.
     *
     * @return array<string, string>
     */
    public function fromInput(mixed $input): array
    {
        if (is_string($input)) {
            return $input === '' ? [] : [$this->currentLocale() => $input];
        }

        if (! is_array($input)) {
            return [];
        }

        $supported = $this->supported();
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
     * The effective global fallback mode from config.
     */
    public function fallbackMode(): FallbackMode
    {
        $configured = config('translatable.fallback');

        if ($configured instanceof FallbackMode) {
            return $configured;
        }

        return FallbackMode::tryFrom((string) $configured) ?? FallbackMode::Any;
    }

    public function fallbackLocale(): string
    {
        return (string) config('translatable.fallback_locale', 'en');
    }

    /**
     * Validation rules for a locale-map field: the field itself plus each per-locale value.
     * When required, at least one locale must be filled. Extra `$each` rules (e.g. `max:120`)
     * are appended to every per-locale value.
     *
     * @param  array<int, mixed>  $each
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $field, bool $required, array $each = []): array
    {
        $rules = [
            $field => $required
                ? array_merge(['required', 'array'], Translations::filledRule($field))
                : ['sometimes', 'array'],
        ];

        foreach ($this->supported() as $locale) {
            $rules["{$field}.{$locale}"] = array_merge(['nullable', 'string'], $each);
        }

        return $rules;
    }

    /**
     * PATCH-merge a set of changes onto a model: only supplied locales are touched.
     */
    public function apply(Translatable $model, TranslationChanges $changes): void
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
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function search(Builder $query, TranslationSearch $search): Builder
    {
        $operator = $query->getModel()->getConnection()->getDriverName() === DatabaseDriver::Pgsql->value ? 'ilike' : 'like';
        // Escape LIKE wildcards so a term like "100%" scopes the (bound) match instead of
        // matching everything; the value stays bound, this only neutralises %/_/\ meaning.
        $term = '%'.addcslashes($search->term, '%_\\').'%';
        $locales = $this->supported();

        return $query->where(static function (Builder $inner) use ($search, $operator, $term, $locales): void {
            foreach ($search->fields as $field) {
                LocaleGuard::ensureIdentifier($field, 'search field');

                foreach ($locales as $locale) {
                    $inner->orWhere("{$field}->".LocaleGuard::ensure($locale), $operator, $term);
                }
            }
        });
    }

    /**
     * Run a callback with the app locale temporarily set to `$locale`, then restore it —
     * a stateless, leak-free way to read a model in another locale (queued renders, sitemaps).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function usingLocale(string $locale, Closure $callback): mixed
    {
        $previous = $this->currentLocale();
        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }
}
