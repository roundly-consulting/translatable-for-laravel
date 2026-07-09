<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Translatable\Commands\SlugIndexesCommand;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Support\ConfigSupportedLocales;
use RoundlyConsulting\Translatable\Support\TranslatableSlug;
use RoundlyConsulting\Translatable\Support\TranslationManager;
use RoundlyConsulting\Translatable\View\Components\TranslationStatus;

final class TranslatableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/translatable.php', 'translatable');

        $this->app->bind(SupportedLocales::class, ConfigSupportedLocales::class);
        $this->app->singleton(TranslationManager::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'translatable');

        $this->registerBlueprintMacros();

        Blade::component('translatable-status', TranslationStatus::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SlugIndexesCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/translatable.php' => config_path('translatable.php'),
            ], 'translatable-config');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/translatable'),
            ], 'translatable-translations');
        }
    }

    private function registerBlueprintMacros(): void
    {
        Blueprint::macro('translatable', function (string $name): ColumnDefinition {
            /** @var Blueprint $this */
            return $this->jsonb($name);
        });

        Blueprint::macro('translatableSlug', function (string $name = 'slug'): ColumnDefinition {
            /** @var Blueprint $this */
            return TranslatableSlug::column($this, $name);
        });
    }
}
