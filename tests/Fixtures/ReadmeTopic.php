<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

/**
 * The README's model, verbatim (only the class name differs), so the README usage example is
 * proven to run. Keep it identical to the README.
 */
final class ReadmeTopic extends Model implements Translatable
{
    use HasTranslations;

    protected $fillable = ['name'];

    /** @var list<string> */
    public array $translatable = ['name'];
}
