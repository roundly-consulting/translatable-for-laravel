<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;
use RoundlyConsulting\Translatable\Enums\FallbackMode;

/**
 * A model overriding the fallback mode and locale per instance (config is ignored).
 */
final class StrictTopic extends Model implements Translatable
{
    use HasTranslations;

    protected $table = 'topics';

    protected $guarded = [];

    /** @var list<string> */
    public $translatable = ['name', 'description', 'slug'];

    protected ?FallbackMode $translatableFallbackMode = FallbackMode::None;

    protected ?string $translatableFallbackLocale = 'sk';
}
