<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\View\Components;

use Illuminate\View\Component;
use RoundlyConsulting\Translatable\Contracts\Translatable;

/**
 * Renders the "which locales are still missing" badge every admin form repeats, built on the
 * model's whole-model status. Usage: `<x-translatable-status :model="$topic" />`. Renders its
 * markup inline so the badge ships self-contained; extend the class to customise the output.
 *
 * @property array<string, list<string>> $missing
 */
final class TranslationStatus extends Component
{
    /**
     * Field => missing locales, limited to fields that actually miss a locale.
     *
     * @var array<string, list<string>>
     */
    public array $missing;

    public function __construct(public Translatable $model)
    {
        $this->missing = array_filter(
            $model->missingTranslations(),
            static fn (array $locales): bool => $locales !== [],
        );
    }

    public function render(): string
    {
        return <<<'blade'
            @if ($missing === [])
                <span class="translatable-status translatable-status--complete">{{ __('translatable::status.complete') }}</span>
            @else
                <span class="translatable-status translatable-status--incomplete">
                    @foreach ($missing as $field => $locales)
                        <span class="translatable-status__field" data-field="{{ $field }}">{{ $field }}: {{ implode(', ', $locales) }}</span>
                    @endforeach
                </span>
            @endif
            blade;
    }
}
