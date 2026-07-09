<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use Illuminate\Support\Str;
use RoundlyConsulting\Translatable\DataTransferObjects\SlugOptions;

/**
 * Pure slug generation: build a base from a source string (or a random fallback),
 * then resolve collisions by suffixing (-2, -3, …) via an injected "exists" callback.
 */
final readonly class SlugGenerator
{
    /**
     * Cap sequential `-2, -3, …` probing before switching to a random suffix, so a seeded
     * run of colliding slugs can't turn each create into an O(N) scan.
     */
    private const MAX_SEQUENTIAL_PROBES = 50;

    public function __construct(
        private SlugOptions $options,
    ) {}

    /**
     * @param  callable(string): bool  $exists  returns true when the candidate is already taken
     */
    public function generate(?string $source, callable $exists): string
    {
        return $this->unique($this->base($source), $exists);
    }

    private function base(?string $source): string
    {
        if ($source === null || trim($source) === '') {
            return $this->random();
        }

        $capped = Str::of($source)
            ->squish()
            ->explode(' ')
            ->take($this->options->maxWords)
            ->implode(' ');

        $slug = Str::slug($capped, $this->options->separator);

        return $slug === '' ? $this->random() : $slug;
    }

    /**
     * @param  callable(string): bool  $exists
     */
    private function unique(string $base, callable $exists): string
    {
        if ($this->available($base, $exists)) {
            return $base;
        }

        for ($suffix = 2; $suffix <= self::MAX_SEQUENTIAL_PROBES + 1; $suffix++) {
            $candidate = $base.$this->options->separator.$suffix;

            if ($this->available($candidate, $exists)) {
                return $candidate;
            }
        }

        do {
            $candidate = $base.$this->options->separator.$this->random();
        } while (! $this->available($candidate, $exists));

        return $candidate;
    }

    /**
     * @param  callable(string): bool  $exists
     */
    private function available(string $candidate, callable $exists): bool
    {
        return ! in_array($candidate, $this->options->reserved, true) && ! $exists($candidate);
    }

    private function random(): string
    {
        return Str::lower(Str::random(8));
    }
}
