<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;
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
 * `strict_locales` comes from an env string. A `(bool)` cast reads "off" and "no" as true, and
 * the `about` row compared with `=== true`, so a string value enforced one thing and reported
 * the other. Both now coerce through filter_var.
 */
it('reads the strict_locales env value as a boolean word', function (string $env, bool $expected): void {
    putenv("TRANSLATABLE_STRICT_LOCALES={$env}");

    try {
        $config = require __DIR__.'/../../config/translatable.php';
    } finally {
        putenv('TRANSLATABLE_STRICT_LOCALES');
    }

    expect($config['strict_locales'])->toBe($expected);
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
