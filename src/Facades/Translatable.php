<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Translatable\Support\TranslationManager;

/**
 * The front door for the locale-map toolkit, over the bound, injectable TranslationManager.
 *
 * No fake: every method is pure locale-map computation (no DB write, queue, mail, event or
 * HTTP call). Pin the locale set in a test with `config()->set('translatable.locales', …)` or
 * a `SupportedLocales` binding; swap in a subclass with `Translatable::swap()` to override one
 * method.
 *
 * @method static list<string> supported()
 * @method static bool isSupported(string $locale)
 * @method static string currentLocale()
 * @method static string ensureLocale(string $locale, bool $strict = false)
 * @method static string|null resolve(array<string, string> $map, string|null $locale = null, \RoundlyConsulting\Translatable\Enums\FallbackMode|null $mode = null, string|null $fallbackLocale = null)
 * @method static array<string, string> fromInput(mixed $input)
 * @method static \RoundlyConsulting\Translatable\Enums\FallbackMode fallbackMode()
 * @method static string fallbackLocale()
 * @method static array<string, array<int, mixed>> rules(string $field, bool $required, array<int, mixed> $each = [])
 * @method static list<\Closure(string, mixed, \Closure): void> filledRule(string $field)
 * @method static void apply(\RoundlyConsulting\Translatable\Contracts\Translatable $model, \RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges $changes)
 * @method static \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model> search(\Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model> $query, \RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch $search)
 * @method static mixed usingLocale(string $locale, \Closure $callback)
 *
 * @see TranslationManager
 */
final class Translatable extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TranslationManager::class;
    }
}
