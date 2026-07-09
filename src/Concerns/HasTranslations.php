<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Exceptions\NotATranslatableAttributeException;
use RoundlyConsulting\Translatable\Support\FallbackResolver;
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
     * @return array<string, mixed>
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
        $locale ??= $this->currentLocale();

        return array_key_exists($locale, $this->readMap($key));
    }

    /**
     * @return list<string>
     */
    public function getTranslatedLocales(string $key): array
    {
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

    public function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes(), true);
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
            if ($value === null || $value === '') {
                continue;
            }

            $map[(string) $locale] = (string) $value;
        }

        return $map;
    }

    /**
     * Write a locale map to the attribute as a plain JSON object, dropping blank values.
     *
     * @param  array<string, string>  $map
     */
    protected function writeMap(string $key, array $map): void
    {
        $filtered = [];

        foreach ($map as $locale => $value) {
            if ($value === '') {
                continue;
            }

            $filtered[(string) $locale] = $value;
        }

        $this->attributes[$key] = json_encode(
            $filtered === [] ? new stdClass : $filtered,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    protected function guardTranslatable(string $key): void
    {
        if (! $this->isTranslatableAttribute($key)) {
            throw NotATranslatableAttributeException::make(static::class, $key);
        }
    }
}
