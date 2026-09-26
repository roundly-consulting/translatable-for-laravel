<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Support\ConfigSupportedLocales;

it('merges the package config with sensible defaults', function (): void {
    expect(config('translatable.locales'))->toBe(['en', 'sk'])
        ->and(config('translatable.fallback'))->toBe(FallbackMode::Any)
        ->and(config('translatable.strict_locales'))->toBeFalse()
        ->and(config('translatable.slug'))->toBeNull();
});

it('binds the default SupportedLocales implementation', function (): void {
    expect(app(SupportedLocales::class))->toBeInstanceOf(ConfigSupportedLocales::class)
        ->and(app(SupportedLocales::class)->supported())->toBe(['en', 'sk']);
});

it('registers no commands of its own', function (): void {
    $ours = array_filter(
        array_keys(app(Kernel::class)->all()),
        static fn (string $name): bool => str_starts_with($name, 'translatable:'),
    );

    expect($ours)->toBe([]);
});

it('registers the translatable blueprint macro and no slug macro', function (): void {
    expect(Blueprint::hasMacro('translatable'))->toBeTrue()
        ->and(Blueprint::hasMacro('translatableSlug'))->toBeFalse();
});

it('exposes the publish groups', function (): void {
    $groups = ServiceProvider::$publishGroups;

    expect($groups)->toHaveKeys(['translatable-config', 'translatable-translations']);
});

it('publishes to the same destinations it always has', function (): void {
    $groups = ServiceProvider::$publishGroups;

    expect(array_values($groups['translatable-config']))->toBe([config_path('translatable.php')])
        ->and(array_keys($groups['translatable-config']))->toBe([realpath(__DIR__.'/../../config/translatable.php')])
        ->and(array_values($groups['translatable-translations']))->toBe([app()->langPath('vendor/translatable')]);
});

/**
 * Fleet policy: a package never auto-loads migrations. This one ships none at all, so the
 * pin is that it stays that way — no migrations directory, and nothing of ours on the
 * migrator's path list.
 */
it('never auto-loads migrations', function (): void {
    /** @var Migrator $migrator */
    $migrator = app('migrator');

    expect(is_dir(__DIR__.'/../../database/migrations'))->toBeFalse();

    foreach ($migrator->paths() as $path) {
        expect($path)->not->toContain('translatable-for-laravel');
    }
});

it('registers the translations under the translatable namespace', function (): void {
    expect(trans('translatable::status.complete'))->not->toBe('translatable::status.complete');
});
