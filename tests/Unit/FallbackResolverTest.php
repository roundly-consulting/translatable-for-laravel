<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Support\FallbackResolver;

beforeEach(function (): void {
    $this->resolver = new FallbackResolver;
    $this->map = ['en' => 'Investing', 'sk' => 'Investovanie'];
});

it('returns the exact locale when present', function (): void {
    expect($this->resolver->resolve($this->map, 'sk', 'en', FallbackMode::Any))->toBe('Investovanie');
});

it('returns null for None when the exact locale is missing', function (): void {
    expect($this->resolver->resolve($this->map, 'de', 'en', FallbackMode::None))->toBeNull();
});

it('returns the fallback locale for Fallback mode', function (): void {
    expect($this->resolver->resolve($this->map, 'de', 'en', FallbackMode::Fallback))->toBe('Investing');
});

it('returns null for Fallback mode when neither exact nor fallback exist', function (): void {
    expect($this->resolver->resolve(['sk' => 'Investovanie'], 'de', 'en', FallbackMode::Fallback))->toBeNull();
});

it('returns the first available value for Any mode', function (): void {
    expect($this->resolver->resolve(['sk' => 'Investovanie'], 'de', 'en', FallbackMode::Any))->toBe('Investovanie');
});

it('returns null for Any mode when the map is empty', function (): void {
    expect($this->resolver->resolve([], 'de', 'en', FallbackMode::Any))->toBeNull();
});

it('ignores blank values at every step', function (): void {
    $map = ['en' => '', 'de' => '', 'sk' => 'Investovanie'];

    expect($this->resolver->resolve($map, 'en', 'de', FallbackMode::Any))->toBe('Investovanie');
});

// Raw maps (a cached payload, an API response) carry nulls and numbers a model map never does.

it('skips a null value when looking for the first available one', function (): void {
    expect($this->resolver->resolve(['en' => null, 'sk' => 'Ahoj'], 'de', 'fr', FallbackMode::Any))->toBe('Ahoj');
});

it('casts int and float values instead of failing the return type', function (): void {
    expect($this->resolver->resolve(['sk' => 5], 'de', 'en', FallbackMode::Any))->toBe('5')
        ->and($this->resolver->resolve(['de' => 5, 'sk' => 'Ahoj'], 'de', 'en', FallbackMode::None))->toBe('5')
        ->and($this->resolver->resolve(['en' => 1.5], 'de', 'en', FallbackMode::Fallback))->toBe('1.5');
});

it('never lets a boolean, array or object win', function (): void {
    $map = ['de' => true, 'en' => ['nested'], 'fr' => new stdClass, 'sk' => 'Ahoj'];

    expect($this->resolver->resolve($map, 'de', 'en', FallbackMode::Any))->toBe('Ahoj')
        ->and($this->resolver->resolve($map, 'de', 'en', FallbackMode::Fallback))->toBeNull();
});

// Any must not depend on the map's key order: jsonb/MySQL JSON re-order keys on the way back.

it('walks Any in the given locale order, not the map order', function (): void {
    $order = ['en', 'sk', 'de'];

    expect($this->resolver->resolve(['de' => 'Hallo', 'sk' => 'Ahoj'], 'fr', 'en', FallbackMode::Any, $order))->toBe('Ahoj')
        ->and($this->resolver->resolve(['sk' => 'Ahoj', 'de' => 'Hallo'], 'fr', 'en', FallbackMode::Any, $order))->toBe('Ahoj');
});

it('walks the locales outside the given order alphabetically', function (): void {
    expect($this->resolver->resolve(['ru' => 'Privet', 'de' => 'Hallo'], 'fr', 'en', FallbackMode::Any, ['en', 'sk']))->toBe('Hallo')
        ->and($this->resolver->resolve(['ru' => 'Privet', 'sk' => ''], 'fr', 'en', FallbackMode::Any, ['en', 'sk']))->toBe('Privet');
});
