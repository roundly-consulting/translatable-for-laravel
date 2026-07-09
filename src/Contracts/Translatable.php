<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Contracts;

/**
 * The public translation API a model gains from the HasTranslations trait.
 *
 * Consumer models `implements Translatable` so the support classes (Translations::apply,
 * validation helpers, …) can type-hint against the surface without touching Eloquent internals.
 */
interface Translatable
{
    public function getTranslation(string $key, ?string $locale = null, bool $useFallback = true): ?string;

    public function setTranslation(string $key, string $locale, ?string $value): static;

    /**
     * @param  array<string, string>  $translations
     */
    public function setTranslations(string $key, array $translations): static;

    /**
     * @param  array<string, array<string, string>>  $fields
     */
    public function replaceTranslations(array $fields): static;

    /**
     * @return ($key is null ? array<string, array<string, string>> : array<string, string>)
     */
    public function getTranslations(?string $key = null): array;

    public function forgetTranslation(string $key, string $locale): static;

    public function forgetAllTranslations(string $key): static;

    public function hasTranslation(string $key, ?string $locale = null): bool;

    /**
     * @return list<string>
     */
    public function getTranslatedLocales(string $key): array;

    /**
     * @return list<string>
     */
    public function missingLocales(string $key): array;

    /**
     * @return array<string, list<string>>
     */
    public function missingTranslations(): array;

    public function isFullyTranslated(?string $key = null): bool;

    public function translationCompleteness(): float;

    public function isTranslatableAttribute(string $key): bool;

    /**
     * @return list<string>
     */
    public function getTranslatableAttributes(): array;

    public function translatedOrNull(string $key): ?string;
}
