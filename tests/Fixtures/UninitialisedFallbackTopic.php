<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;
use RoundlyConsulting\Translatable\Enums\FallbackMode;

/**
 * Typed per-model overrides with no default — never initialised, so reading them directly
 * is an `Error`. They must mean "use the config", like `null`.
 */
final class UninitialisedFallbackTopic extends Model implements Translatable
{
    use HasTranslations;

    protected $table = 'topics';

    protected $guarded = [];

    /** @var list<string> */
    public $translatable = ['name'];

    protected ?FallbackMode $translatableFallbackMode;

    protected ?string $translatableFallbackLocale;
}
