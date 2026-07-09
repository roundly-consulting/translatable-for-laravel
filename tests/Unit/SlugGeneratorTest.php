<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\DataTransferObjects\SlugOptions;
use RoundlyConsulting\Translatable\Support\SlugGenerator;

function slugOptions(array $overrides = []): SlugOptions
{
    return new SlugOptions(
        sourceField: $overrides['sourceField'] ?? 'name',
        separator: $overrides['separator'] ?? '-',
        maxWords: $overrides['maxWords'] ?? 12,
        reserved: $overrides['reserved'] ?? [],
    );
}

$never = fn (string $candidate): bool => false;

it('slugifies the source string', function () use ($never): void {
    $generator = new SlugGenerator(slugOptions());

    expect($generator->generate('Investing Basics', $never))->toBe('investing-basics');
});

it('caps the input to the configured word count', function () use ($never): void {
    $generator = new SlugGenerator(slugOptions(['maxWords' => 2]));

    expect($generator->generate('one two three four', $never))->toBe('one-two');
});

it('honours a custom separator', function () use ($never): void {
    $generator = new SlugGenerator(slugOptions(['separator' => '_']));

    expect($generator->generate('Hello World', $never))->toBe('hello_world');
});

it('suffixes on collision until a free slug is found', function (): void {
    $taken = ['investing', 'investing-2'];
    $generator = new SlugGenerator(slugOptions());

    $slug = $generator->generate('Investing', fn (string $candidate): bool => in_array($candidate, $taken, true));

    expect($slug)->toBe('investing-3');
});

it('skips reserved slugs', function () use ($never): void {
    $generator = new SlugGenerator(slugOptions(['reserved' => ['edit']]));

    expect($generator->generate('Edit', $never))->toBe('edit-2');
});

it('generates a random slug when there is no source', function () use ($never): void {
    $generator = new SlugGenerator(slugOptions());

    expect($generator->generate(null, $never))->toMatch('/^[a-z0-9]{8}$/')
        ->and($generator->generate('   ', $never))->toMatch('/^[a-z0-9]{8}$/');
});

it('generates a random slug when the source slugifies to nothing', function () use ($never): void {
    $generator = new SlugGenerator(slugOptions());

    expect($generator->generate('###', $never))->toMatch('/^[a-z0-9]{8}$/');
});
