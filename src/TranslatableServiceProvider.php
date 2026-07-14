<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Blade;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\Translatable\Commands\SlugIndexesCommand;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Support\ConfigSupportedLocales;
use RoundlyConsulting\Translatable\Support\TranslatableSlug;
use RoundlyConsulting\Translatable\Support\TranslationManager;
use RoundlyConsulting\Translatable\View\Components\TranslationStatus;

final class TranslatableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('translatable')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasCommands([
                SlugIndexesCommand::class,
            ])
            ->contributesToAbout($this->aboutSection(...));
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(SupportedLocales::class, ConfigSupportedLocales::class);
        $this->app->singleton(TranslationManager::class);

        // Registered here rather than in boot(): the macros must exist before anything can
        // build a schema with them, and a host's migration may run against a provider that
        // has registered but not yet booted.
        $this->registerBlueprintMacros();
    }

    public function boot(): void
    {
        parent::boot();

        Blade::component('translatable-status', TranslationStatus::class);
    }

    /**
     * The `php artisan about` payload. Reports shape, never content: the locale list is the
     * host's market footprint and reserved slugs name the routes it protects, so both render
     * as counts. Runs no query.
     *
     * @return array<string, string>
     */
    private function aboutSection(): array
    {
        $locales = config('translatable.locales', []);
        $slug = config('translatable.slug', []);

        $reserved = is_array($slug) && is_array($slug['reserved'] ?? null) ? $slug['reserved'] : [];
        $sourceField = is_array($slug) ? ($slug['source_field'] ?? 'name') : 'name';

        return [
            'Locales' => (is_array($locales) ? count($locales) : 0).' configured',
            'Locales source' => class_basename($this->app->make(SupportedLocales::class)),
            'Fallback' => $this->app->make(TranslationManager::class)->fallbackMode()->value,
            'Fallback locale' => config('translatable.fallback_locale') === null ? 'DEFAULT' : 'SET',
            'Strict locales' => config('translatable.strict_locales') === true ? 'ON' : 'OFF',
            'Slug source field' => $sourceField === 'name' ? 'DEFAULT' : 'CUSTOM',
            'Slug separator' => is_array($slug) && ($slug['separator'] ?? '-') === '-' ? 'DEFAULT' : 'CUSTOM',
            'Slug max words' => (string) (is_array($slug) ? (int) ($slug['max_words'] ?? 12) : 12),
            'Reserved slugs' => $reserved === [] ? 'NONE' : count($reserved).' reserved',
        ];
    }

    /**
     * The package's own schema macros — `$table->translatable('name')` and
     * `$table->translatableSlug()`. Not the toolkit's: it ships key-type/morph/audit macros
     * and owns neither of these, so there is nothing here to delegate to. Guarded, so a
     * double-registered provider never re-registers them.
     */
    private function registerBlueprintMacros(): void
    {
        if (! Blueprint::hasMacro('translatable')) {
            Blueprint::macro('translatable', function (string $name): ColumnDefinition {
                /** @var Blueprint $this */
                return $this->jsonb($name);
            });
        }

        if (! Blueprint::hasMacro('translatableSlug')) {
            Blueprint::macro('translatableSlug', function (string $name = 'slug'): ColumnDefinition {
                /** @var Blueprint $this */
                return TranslatableSlug::column($this, $name);
            });
        }
    }
}
