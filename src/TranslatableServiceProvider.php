<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Blade;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\Sluggable\Contracts\SlugLocales;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Support\ConfigSupportedLocales;
use RoundlyConsulting\Translatable\Support\TranslatableSlugLocales;
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
            ->contributesToAbout($this->aboutSection(...));
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(SupportedLocales::class, ConfigSupportedLocales::class);
        $this->app->singleton(TranslationManager::class);

        // One locale source of truth for slugs and translations. sluggable registers its
        // config default with bindIf(), so this wins in either provider order; a host binding
        // in a later provider wins over both.
        $this->app->bind(SlugLocales::class, TranslatableSlugLocales::class);

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
     * host's market footprint, so it renders as a count and the fallback locale as SET/DEFAULT.
     * Runs no query.
     *
     * @return array<string, string>
     */
    private function aboutSection(): array
    {
        $locales = config('translatable.locales', []);

        return [
            'Locales' => (is_array($locales) ? count($locales) : 0).' configured',
            'Locales source' => class_basename($this->app->make(SupportedLocales::class)),
            'Fallback' => $this->app->make(TranslationManager::class)->fallbackMode()->value,
            'Fallback locale' => config('translatable.fallback_locale') === null ? 'DEFAULT' : 'SET',
            'Strict locales' => config('translatable.strict_locales') === true ? 'ON' : 'OFF',
        ];
    }

    /**
     * The package's own schema macro — `$table->translatable('name')`. Not the toolkit's: it
     * ships key-type/morph/audit macros and owns none of this. Slug columns come from
     * sluggable (`$table->localizedSlug()`). Guarded, so a double-registered provider never
     * re-registers it.
     */
    private function registerBlueprintMacros(): void
    {
        if (! Blueprint::hasMacro('translatable')) {
            Blueprint::macro('translatable', function (string $name): ColumnDefinition {
                /** @var Blueprint $this */
                return $this->jsonb($name);
            });
        }
    }
}
