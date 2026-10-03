<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;
use RoundlyConsulting\Translatable\Facades\Translatable;
use RoundlyConsulting\Translatable\Support\ConfigSupportedLocales;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

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

/**
 * `strict_locales` comes from an env string. A `(bool)` cast reads "off" and "no" as true, and a
 * `filter_var` in the config file read a typo such as "disabled" as false. The file now hands
 * the raw env value over, and every reader parses it strictly through `Config::boolean()`.
 *
 * @param  array<string, string>  $env
 * @return array<string, mixed>
 */
function translatableConfigFromEnv(array $env): array
{
    foreach ($env as $name => $value) {
        putenv("{$name}={$value}");
    }

    try {
        return require __DIR__.'/../../config/translatable.php';
    } finally {
        foreach (array_keys($env) as $name) {
            putenv($name);
        }
    }
}

it('reads the strict_locales env value as a boolean word', function (string $env, bool $expected): void {
    config()->set('translatable.strict_locales', translatableConfigFromEnv(['TRANSLATABLE_STRICT_LOCALES' => $env])['strict_locales']);

    expect(Config::boolean('translatable.strict_locales'))->toBe($expected);
})->with([
    ['off', false],
    ['no', false],
    ['0', false],
    ['false', false],
    ['on', true],
    ['yes', true],
    ['1', true],
    ['true', true],
]);

it('hands a strict_locales typo to the reader raw, which throws (strict config)', function (): void {
    $config = translatableConfigFromEnv(['TRANSLATABLE_STRICT_LOCALES' => 'disabled']);
    config()->set('translatable.strict_locales', $config['strict_locales']);

    expect($config['strict_locales'])->toBe('disabled')
        ->and(fn () => new Topic(['name' => ['de' => 'Investieren']]))->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [translatable.strict_locales] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.',
        );
});

it('hands a fallback mode typo to the reader raw, which throws (strict config)', function (): void {
    $config = translatableConfigFromEnv(['TRANSLATABLE_FALLBACK' => 'fallbak']);
    config()->set('translatable.fallback', $config['fallback']);

    expect($config['fallback'])->toBe('fallbak')
        ->and(fn () => Translatable::fallbackMode())->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [translatable.fallback] must be one of [none, fallback, any], [fallbak] given.',
        );
});

it('reads a fallback mode from the env', function (): void {
    config()->set('translatable.fallback', translatableConfigFromEnv(['TRANSLATABLE_FALLBACK' => 'none'])['fallback']);

    expect(Translatable::fallbackMode())->toBe(FallbackMode::None);
});

it('defaults the fallback mode to any when unset', function (): void {
    expect(translatableConfigFromEnv([])['fallback'])->toBe(FallbackMode::Any);

    config()->set('translatable.fallback', null);

    expect(Translatable::fallbackMode())->toBe(FallbackMode::Any);
});

it('enforces and reports a string strict_locales value the same way', function (string $configured, bool $strict): void {
    config()->set('translatable.strict_locales', $configured);

    Artisan::call('about', ['--only' => 'translatable']);
    $row = collect(explode("\n", Artisan::output()))
        ->first(static fn (string $line): bool => str_contains($line, 'Strict locales'));
    $write = fn () => new Topic(['name' => ['de' => 'Investieren']]);

    expect(trim((string) $row))->toEndWith($strict ? ' ON' : ' OFF');

    $strict
        ? expect($write)->toThrow(InvalidLocaleException::class)
        : expect($write()->getTranslations('name'))->toBe(['de' => 'Investieren']);
})->with([
    ['off', false],
    ['no', false],
    ['yes', true],
    ['on', true],
]);
