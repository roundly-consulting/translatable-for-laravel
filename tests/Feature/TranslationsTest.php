<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

dataset('translation files', ['status', 'validation']);

/**
 * Every language ships the same keys and the same `:placeholders` as English.
 */
it('ships the same keys in every language', function (string $file): void {
    $en = Arr::dot(require __DIR__.'/../../resources/lang/en/'.$file.'.php');
    $sk = Arr::dot(require __DIR__.'/../../resources/lang/sk/'.$file.'.php');

    expect($en)->not->toBeEmpty()
        ->and(array_keys($sk))->toBe(array_keys($en));
})->with('translation files');

it('keeps every placeholder in every language', function (string $file): void {
    $en = Arr::dot(require __DIR__.'/../../resources/lang/en/'.$file.'.php');
    $sk = Arr::dot(require __DIR__.'/../../resources/lang/sk/'.$file.'.php');

    $placeholders = static function (mixed $line): array {
        preg_match_all('/:(\w+)/', (string) $line, $matches);
        $names = array_values(array_unique($matches[1]));
        sort($names);

        return $names;
    };

    foreach ($en as $key => $line) {
        expect($placeholders($sk[$key] ?? ''))->toBe($placeholders($line), "{$file}.{$key}");
    }
})->with('translation files');

it('loads slovak and english under the translatable namespace', function (): void {
    app()->setLocale('en');

    expect(trans('translatable::validation.filled'))
        ->toBe('At least one locale must be provided.');

    app()->setLocale('sk');

    expect(trans('translatable::validation.filled'))
        ->toBe('Je potrebné vyplniť aspoň jednu jazykovú verziu.');
});
