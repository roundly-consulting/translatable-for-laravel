<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use RoundlyConsulting\Translatable\Tests\Fixtures\Topic;

beforeEach(function (): void {
    app()->setLocale('en');
});

it('renders the missing locales for a partial model', function (): void {
    $topic = new Topic(['name' => ['en' => 'Investing'], 'slug' => ['en' => 'investing']]);

    $html = Blade::render('<x-translatable-status :model="$model" />', ['model' => $topic]);

    expect($html)->toContain('translatable-status--incomplete')
        ->toContain('name: sk')
        ->toContain('description: en, sk');
});

it('renders the complete state for a fully translated model', function (): void {
    $topic = new Topic([
        'name' => ['en' => 'Investing', 'sk' => 'Investovanie'],
        'description' => ['en' => 'A guide', 'sk' => 'Sprievodca'],
        'slug' => ['en' => 'investing', 'sk' => 'investovanie'],
    ]);

    $html = Blade::render('<x-translatable-status :model="$model" />', ['model' => $topic]);

    expect($html)->toContain('translatable-status--complete')
        ->toContain('All locales translated')
        ->not->toContain('translatable-status--incomplete');
});
