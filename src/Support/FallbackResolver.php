<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use RoundlyConsulting\Translatable\Enums\FallbackMode;

/**
 * Pure resolution of a locale map to a single value, driven by a FallbackMode.
 * Unit-testable independent of Eloquent.
 */
final readonly class FallbackResolver
{
    /**
     * @param  array<string, string>  $map
     */
    public function resolve(array $map, string $locale, string $fallback, FallbackMode $mode): ?string
    {
        $exact = $this->nonBlank($map, $locale);

        if ($exact !== null) {
            return $exact;
        }

        if ($mode === FallbackMode::None) {
            return null;
        }

        $fallbackValue = $this->nonBlank($map, $fallback);

        if ($fallbackValue !== null) {
            return $fallbackValue;
        }

        if ($mode === FallbackMode::Any) {
            foreach ($map as $value) {
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function nonBlank(array $map, string $locale): ?string
    {
        $value = $map[$locale] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
