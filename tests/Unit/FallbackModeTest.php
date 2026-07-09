<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\Enums\FallbackMode;

it('exposes the three backed values', function (): void {
    expect(FallbackMode::None->value)->toBe('none')
        ->and(FallbackMode::Fallback->value)->toBe('fallback')
        ->and(FallbackMode::Any->value)->toBe('any');
});

it('coerces a string value via tryFrom', function (): void {
    expect(FallbackMode::tryFrom('any'))->toBe(FallbackMode::Any)
        ->and(FallbackMode::tryFrom('nope'))->toBeNull();
});

it('exposes DX helpers from the enums-for-laravel Helpers trait', function (): void {
    expect(FallbackMode::options())->toHaveCount(3)
        ->and(FallbackMode::Any->readable())->toBe('Any')
        ->and(FallbackMode::values()->all())->toBe(['none', 'fallback', 'any']);
});
