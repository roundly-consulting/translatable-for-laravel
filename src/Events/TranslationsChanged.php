<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched once per persisted save when a model's translatable attributes changed, so a host
 * (or an AI auto-fill service) can react to `missingLocales()` without overriding the model.
 */
final readonly class TranslationsChanged
{
    /**
     * @param  list<string>  $changedAttributes
     */
    public function __construct(
        public Model $model,
        public array $changedAttributes,
    ) {}
}
