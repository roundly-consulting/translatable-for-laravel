<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Translatable\Commands\SlugIndexesCommand;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Support\ConfigSupportedLocales;

it('merges the package config with sensible defaults', function (): void {
    expect(config('translatable.locales'))->toBe(['en', 'sk'])
        ->and(config('translatable.fallback'))->toBe(FallbackMode::Any)
        ->and(config('translatable.slug.source_field'))->toBe('name');
});

it('binds the default SupportedLocales implementation', function (): void {
    expect(app(SupportedLocales::class))->toBeInstanceOf(ConfigSupportedLocales::class)
        ->and(app(SupportedLocales::class)->supported())->toBe(['en', 'sk']);
});

it('registers the slug-indexes command', function (): void {
    $commands = app(Kernel::class)->all();

    expect($commands)->toHaveKey('translatable:slug-indexes')
        ->and($commands['translatable:slug-indexes'])->toBeInstanceOf(SlugIndexesCommand::class);
});

it('registers the blueprint macros', function (): void {
    expect(Blueprint::hasMacro('translatable'))->toBeTrue()
        ->and(Blueprint::hasMacro('translatableSlug'))->toBeTrue();
});

it('exposes the publish groups', function (): void {
    $groups = ServiceProvider::$publishGroups;

    expect($groups)->toHaveKeys(['translatable-config', 'translatable-translations']);
});
