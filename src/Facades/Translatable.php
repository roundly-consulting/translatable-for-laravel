<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Translatable\Support\TranslationManager;

/**
 * The discoverable front door for the locale-map toolkit, over the bound TranslationManager.
 *
 * @method static list<string> supported()
 * @method static string currentLocale()
 * @method static array<string, string> fromInput(mixed $input)
 * @method static \RoundlyConsulting\Translatable\Enums\FallbackMode fallbackMode()
 * @method static string fallbackLocale()
 * @method static array<string, array<int, mixed>> rules(string $field, bool $required, array<int, mixed> $each = [])
 * @method static void apply(\RoundlyConsulting\Translatable\Contracts\Translatable $model, \RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges $changes)
 * @method static \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> search(\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $query, \RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch $search)
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
