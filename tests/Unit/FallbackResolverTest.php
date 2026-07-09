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
