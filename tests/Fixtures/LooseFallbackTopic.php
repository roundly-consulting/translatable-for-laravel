<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

/**
 * Per-model fallback overrides declared without a type, the way a host writes
 * `protected $translatableFallbackMode = 'none';`. `overrideFallback()` lets a test try other
 * values on the same declaration.
 */
final class LooseFallbackTopic extends Model implements Translatable
{
    use HasTranslations;

    protected $table = 'topics';

    protected $guarded = [];

    /** @var list<string> */
    public $translatable = ['name'];

    /** @var mixed */
    protected $translatableFallbackMode = 'none';

    /** @var mixed */
    protected $translatableFallbackLocale = null;

    public function overrideFallback(mixed $mode, mixed $locale = null): static
    {
        $this->translatableFallbackMode = $mode;
        $this->translatableFallbackLocale = $locale;

        return $this;
    }
}
