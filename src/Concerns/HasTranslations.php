<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;
use RoundlyConsulting\Translatable\Exceptions\InvalidTranslationValueException;
use RoundlyConsulting\Translatable\Exceptions\NotATranslatableAttributeException;
use RoundlyConsulting\Translatable\Exceptions\TranslatableException;
use RoundlyConsulting\Translatable\Support\FallbackResolver;
use RoundlyConsulting\Translatable\Support\LocaleGuard;
use RoundlyConsulting\Translatable\Support\Translations;
use stdClass;

/**
 * Gives an Eloquent model locale-map attributes stored as a plain JSON object.
 * The trait owns JSON serialization for its listed attributes — models add no cast.
 *
 * A model lists translatable attributes in a `public array $translatable` property and may
 * declare `protected ?FallbackMode $translatableFallbackMode` /
 * `protected ?string $translatableFallbackLocale` to override the config defaults.
 *
 * @property array<int, string> $translatable
 *
 * @phpstan-require-extends Model
 */
trait HasTranslations
{
    public function getAttributeValue($key): mixed
    {
        if ($this->isTranslatableAttribute($key)) {
            return $this->getTranslation($key, $this->currentLocale(), true);
        }

        return parent::getAttributeValue($key);
    }

    /**
     * Serialize translatable attributes as their resolved locale value (matching `$model->name`)
     * rather than the raw stored JSON map, so `toArray()`/`toJson()`/API Resources agree with
     * property access. Persisted data is untouched — this changes output shape only.
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $array = parent::attributesToArray();

        foreach ($this->getTranslatableAttributes() as $attribute) {
            if (array_key_exists($attribute, $array)) {
                $array[$attribute] = $this->getTranslation($attribute, $this->currentLocale(), true);
            }
        }

        return $array;
    }

    public function setAttribute($key, $value)
    {
        if ($this->isTranslatableAttribute($key)) {
            if (is_array($value)) {
                /** @var array<string, string> $value */
                $this->writeMap($key, $value);
            } else {
                $this->setTranslation($key, $this->currentLocale(), $value === null ? null : (string) $value);
            }

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function getTranslation(string $key, ?string $locale = null, bool $useFallback = true): ?string
    {
        $this->guardTranslatable($key);

        $locale ??= $this->currentLocale();
        $mode = $useFallback ? $this->translationFallbackMode() : FallbackMode::None;

        return (new FallbackResolver)->resolve(
            $this->readMap($key),
            $locale,
            $this->translationFallbackLocale(),
            $mode,
        );
    }

    public function translatedOrNull(string $key): ?string
    {
        return $this->getTranslation($key, $this->currentLocale(), false);
    }

    public function setTranslation(string $key, string $locale, ?string $value): static
    {
        $this->guardTranslatable($key);
        $this->guardLocale($locale);

        $map = $this->readMap($key);

        if ($value === null || $value === '') {
            unset($map[$locale]);
        } else {
            $map[$locale] = $value;
        }

        $this->writeMap($key, $map);

        return $this;
    }

    /**
     * @param  array<string, string>  $translations
     */
    public function setTranslations(string $key, array $translations): static
    {
        $this->guardTranslatable($key);
        $this->writeMap($key, $translations);

        return $this;
    }

    /**
     * @param  array<string, array<string, string>>  $fields
     */
    public function replaceTranslations(array $fields): static
    {
        foreach ($fields as $key => $translations) {
            $this->setTranslations($key, $translations);
        }

        return $this;
    }

    /**
     * @return ($key is null ? array<string, array<string, string>> : array<string, string>)
     */
    public function getTranslations(?string $key = null): array
    {
        if ($key !== null) {
            $this->guardTranslatable($key);

            return $this->readMap($key);
        }

        $all = [];

        foreach ($this->getTranslatableAttributes() as $attribute) {
            $all[$attribute] = $this->readMap($attribute);
        }

        return $all;
    }

    public function forgetTranslation(string $key, string $locale): static
    {
        return $this->setTranslation($key, $locale, null);
    }

    public function forgetAllTranslations(string $key): static
    {
        return $this->setTranslations($key, []);
    }

    public function hasTranslation(string $key, ?string $locale = null): bool
    {
        $this->guardTranslatable($key);

        $locale ??= $this->currentLocale();

        return array_key_exists($locale, $this->readMap($key));
    }

    /**
     * @return list<string>
     */
    public function getTranslatedLocales(string $key): array
    {
        $this->guardTranslatable($key);

        return array_keys($this->readMap($key));
    }

    /**
     * @return list<string>
     */
    public function missingLocales(string $key): array
    {
        $this->guardTranslatable($key);

        return array_values(array_diff(Translations::supported(), $this->getTranslatedLocales($key)));
    }

    /**
     * Missing locales for every translatable attribute (minus the status-excluded ones).
     *
     * @return array<string, list<string>>
     */
    public function missingTranslations(): array
    {
        $missing = [];

        foreach ($this->translationStatusAttributes() as $attribute) {
            $missing[$attribute] = $this->missingLocales($attribute);
        }

        return $missing;
    }

    /**
     * Whether one attribute (or every translatable attribute) has every supported locale.
     */
    public function isFullyTranslated(?string $key = null): bool
    {
        if ($key !== null) {
            return $this->missingLocales($key) === [];
        }

        foreach ($this->translationStatusAttributes() as $attribute) {
            if ($this->missingLocales($attribute) !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fraction (0.0–1.0) of supported locale slots filled across the translatable attributes —
     * ready for a progress bar. Empty of status attributes counts as fully translated (1.0).
     */
    public function translationCompleteness(): float
    {
        $attributes = $this->translationStatusAttributes();
        $locales = Translations::supported();
        $total = count($attributes) * count($locales);

        if ($total === 0) {
            return 1.0;
        }

        $filled = 0;

        foreach ($attributes as $attribute) {
            $filled += count(array_intersect($locales, $this->getTranslatedLocales($attribute)));
        }

        return $filled / $total;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWhereLocale(Builder $query, string $field, string $value, ?string $locale = null): Builder
    {
        $locale = $this->guardScope($field, $locale);

        return $query->where("{$field}->{$locale}", $value);
    }

    /**
     * Rows that carry a non-blank value for the given locale of the field.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWhereHasLocale(Builder $query, string $field, ?string $locale = null): Builder
    {
        $locale = $this->guardScope($field, $locale);

        return $query->where(function (Builder $inner) use ($field, $locale): void {
            $inner->whereNotNull("{$field}->{$locale}")
                ->where("{$field}->{$locale}", '!=', '');
        });
    }

    /**
     * Rows missing the given locale of the field (feeds an AI auto-fill queue).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWhereMissingLocale(Builder $query, string $field, ?string $locale = null): Builder
    {
        $locale = $this->guardScope($field, $locale);

        return $query->where(function (Builder $inner) use ($field, $locale): void {
            $inner->whereNull("{$field}->{$locale}")
                ->orWhere("{$field}->{$locale}", '');
        });
    }

    public function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes(), true);
    }

    /**
     * Translatable attributes counted for whole-model status, minus the excluded ones.
     *
     * @return list<string>
     */
    protected function translationStatusAttributes(): array
    {
        return array_values(array_diff($this->getTranslatableAttributes(), $this->translationStatusExcludes()));
    }

    /**
     * Attributes excluded from whole-model status (e.g. a slug column). Override to customise.
     *
     * @return list<string>
     */
    protected function translationStatusExcludes(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function getTranslatableAttributes(): array
    {
        return array_values($this->translatable);
    }

    public function translationFallbackMode(): FallbackMode
    {
        if (property_exists($this, 'translatableFallbackMode') && $this->translatableFallbackMode instanceof FallbackMode) {
            return $this->translatableFallbackMode;
        }

        $configured = config('translatable.fallback');

        if ($configured instanceof FallbackMode) {
            return $configured;
        }

        return FallbackMode::tryFrom((string) $configured) ?? FallbackMode::Any;
    }

    public function translationFallbackLocale(): string
    {
        if (property_exists($this, 'translatableFallbackLocale') && is_string($this->translatableFallbackLocale)) {
            return $this->translatableFallbackLocale;
        }

        return (string) config('translatable.fallback_locale', 'en');
    }

    protected function currentLocale(): string
    {
        return app()->getLocale();
    }

    /**
     * Read the stored locale map for an attribute, dropping any blank values.
     *
     * @return array<string, string>
     */
    protected function readMap(string $key): array
    {
        $raw = $this->attributes[$key] ?? null;

        if ($raw === null) {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $map = [];

        foreach ($decoded as $locale => $value) {
            // Skip nested arrays/objects that can't be represented as a locale string,
            // rather than `(string)`-casting them into an "Array to string" warning.
            if (! is_scalar($value) || $value === '') {
                continue;
            }

            $map[(string) $locale] = (string) $value;
        }

        return $map;
    }

    /**
     * Write a locale map to the attribute as a plain JSON object, dropping blank values.
     * Locale keys are validated (format, and supported-membership in strict mode) and values
     * must be scalars — arrays/objects/booleans are rejected rather than silently coerced.
     *
     * @param  array<string, mixed>  $map
     */
    protected function writeMap(string $key, array $map): void
    {
        $filtered = [];

        foreach ($map as $locale => $value) {
            $locale = (string) $locale;
            $this->guardLocale($locale);

            if ($value === null || $value === '') {
                continue;
            }

            $filtered[$locale] = $this->normalizeTranslationValue($locale, $value);
        }

        try {
            $encoded = json_encode(
                $filtered === [] ? new stdClass : $filtered,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new TranslatableException(
                "Failed to encode translations for [{$key}]: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        $this->attributes[$key] = $encoded;
    }

    /**
     * Coerce a single map value to the stored string form, allowing strings and casting
     * int/float; anything else (arrays, objects, booleans, resources) is rejected.
     */
    private function normalizeTranslationValue(string $locale, mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw InvalidTranslationValueException::make($locale, get_debug_type($value));
    }

    /**
     * Validate a locale key on a write path. Malformed keys are always rejected; when
     * `translatable.strict_locales` is on, the key must also be a supported locale.
     */
    protected function guardLocale(string $locale): void
    {
        $strict = (bool) config('translatable.strict_locales', false);

        LocaleGuard::ensure($locale, $strict, $strict ? Translations::supported() : null);
    }

    /**
     * Validate a scope's field (must be translatable) and locale (must be well-formed),
     * returning the effective locale. Keeps request-supplied `?locale=` values from ever
     * reaching a `{$field}->{$locale}` JSON-path column expression unchecked.
     */
    protected function guardScope(string $field, ?string $locale): string
    {
        $this->guardTranslatable($field);

        $locale ??= $this->currentLocale();

        if (! LocaleGuard::isValid($locale)) {
            throw InvalidLocaleException::forFormat($locale);
        }

        return $locale;
    }

    protected function guardTranslatable(string $key): void
    {
        if (! $this->isTranslatableAttribute($key)) {
            throw NotATranslatableAttributeException::make(static::class, $key);
        }
    }
}
