<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;

it('builds TranslationChanges', function (): void {
    $changes = TranslationChanges::make(['name' => ['en' => 'Hi']]);

    expect($changes->fields)->toBe(['name' => ['en' => 'Hi']]);
});

it('builds TranslationSearch', function (): void {
    $search = new TranslationSearch(fields: ['name', 'description'], term: 'invest');

    expect($search->fields)->toBe(['name', 'description'])
        ->and($search->term)->toBe('invest');
});
