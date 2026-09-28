<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use RoundlyConsulting\Translatable\Enums\FallbackMode;

/**
 * Pure resolution of a locale map to a single value, driven by a FallbackMode.
 * Unit-testable independent of Eloquent.
 *
 * @internal building block — hosts call `Translatable::resolve()`, which fills in the current
 *           locale, the configured fallback settings and the supported-locale order.
 */
final readonly class FallbackResolver
{
    /**
     * Only readable values win (see TranslationValue): a raw map from a cache or an API may
     * carry nulls or numbers. `Any` walks `$order` first (the supported locales), then the
     * remaining locales alphabetically — never the map's own key order, which PostgreSQL jsonb
     * and MySQL JSON rewrite, so the answer is the same on every engine and after a reload.
     *
     * @param  array<array-key, mixed>  $map
     * @param  list<string>  $order
     */
    public function resolve(array $map, string $locale, string $fallback, FallbackMode $mode, array $order = []): ?string
    {
        $exact = $this->valueAt($map, $locale);

        if ($exact !== null || $mode === FallbackMode::None) {
            return $exact;
        }

        $fallbackValue = $this->valueAt($map, $fallback);

        if ($fallbackValue !== null || $mode !== FallbackMode::Any) {
            return $fallbackValue;
        }

        $rest = array_values(array_diff(array_map(strval(...), array_keys($map)), $order));
        sort($rest, SORT_STRING);

        foreach ([...$order, ...$rest] as $candidate) {
            $value = $this->valueAt($map, $candidate);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $map
     */
    private function valueAt(array $map, string $locale): ?string
    {
        return TranslationValue::readable($map[$locale] ?? null);
    }
}
