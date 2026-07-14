<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/**
 * The config-key contract, pinned in BOTH directions:
 *
 *  - forward  — every `translatable.*` key the source reads must exist in the shipped config,
 *    or a host that publishes the file can never reach the feature;
 *  - reverse  — every key the config file ships must be READ somewhere in the source, or it
 *    is a documented feature that silently does nothing (the fleet has shipped four of those).
 *
 * Both directions scrape PHP **string tokens**, never raw file text: a docblock that mentions
 * a key is not a read, and a regex over the file would let one satisfy the reverse direction
 * vacuously.
 */

/** @return list<string> */
function stringTokensIn(string $file): array
{
    $tokens = token_get_all((string) file_get_contents($file));
    $strings = [];

    foreach ($tokens as $token) {
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $strings[] = substr($token[1], 1, -1);
        }
    }

    return $strings;
}

/** @return list<string> */
function sourceFiles(?string $except = null): array
{
    /** @var list<string> $files */
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/../../src', RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php' && $file->getFilename() !== $except) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/** Every dotted `translatable.…` key the source actually reads. @return list<string> */
function configKeysRead(?string $exceptFile = null): array
{
    $keys = [];

    foreach (sourceFiles($exceptFile) as $file) {
        foreach (stringTokensIn($file) as $string) {
            // Key SHAPE, not merely the prefix — an exception message that happens to open
            // with "translatable.strict_locales is enabled." is prose, not a config read.
            if (preg_match('/^translatable\.[a-z0-9_]+(\.[a-z0-9_]+)*$/', $string) === 1) {
                $keys[] = $string;
            }
        }
    }

    return array_values(array_unique($keys));
}

/**
 * Every leaf key the config file ships, dotted. A list (`locales`, `slug.reserved`) is itself
 * a leaf — its elements are values, not keys — so recursion stops at any non-associative array.
 *
 * @param  array<array-key, mixed>  $config
 * @return list<string>
 */
function configLeaves(array $config, string $prefix = ''): array
{
    $leaves = [];

    foreach ($config as $key => $value) {
        $dotted = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value) && $value !== [] && ! array_is_list($value)) {
            $leaves = [...$leaves, ...configLeaves($value, $dotted)];

            continue;
        }

        $leaves[] = $dotted;
    }

    return $leaves;
}

/** @return list<string> */
function shippedConfigLeaves(): array
{
    /** @var array<string, mixed> $config */
    $config = require __DIR__.'/../../config/translatable.php';

    return configLeaves($config);
}

it('reads no config key it does not ship', function (): void {
    $shipped = require __DIR__.'/../../config/translatable.php';

    expect(configKeysRead())->not->toBeEmpty();

    foreach (configKeysRead() as $key) {
        $relative = substr($key, strlen('translatable.'));

        expect(Arr::has($shipped, $relative))->toBeTrue("config/translatable.php does not ship [{$key}], but the source reads it.");
    }
});

it('ships no config key it never reads', function (): void {
    // The provider is excluded on purpose: it renders `php artisan about`, and a key that is
    // only ever *displayed* is still a key nothing acts on. Every shipped key must be read by
    // real code.
    $read = configKeysRead(exceptFile: 'TranslatableServiceProvider.php');

    /** @var list<string> $tokens */
    $tokens = [];

    foreach (sourceFiles('TranslatableServiceProvider.php') as $file) {
        $tokens = [...$tokens, ...stringTokensIn($file)];
    }

    $leaves = shippedConfigLeaves();
    expect($leaves)->not->toBeEmpty();

    foreach ($leaves as $leaf) {
        $dotted = 'translatable.'.$leaf;

        // Either the leaf is read by its full dotted key, or its parent section is read as an
        // array and the leaf name is used to index it (SlugOptions::fromConfig()).
        $segments = explode('.', $leaf);
        $leafName = array_pop($segments);
        $parent = $segments === [] ? null : 'translatable.'.implode('.', $segments);

        $readDirectly = in_array($dotted, $read, true);
        $readViaParent = $parent !== null && in_array($parent, $read, true) && in_array($leafName, $tokens, true);

        expect($readDirectly || $readViaParent)->toBeTrue("config/translatable.php ships [{$dotted}], but no code reads it.");
    }
});
