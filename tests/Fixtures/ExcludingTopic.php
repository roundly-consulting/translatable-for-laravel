<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

/**
 * A translatable model that excludes its slug column from whole-model status, so a
 * "content complete" bar ignores slugs.
 *
 * @property int $id
 */
final class ExcludingTopic extends Model implements Translatable
{
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'topics';

    protected $guarded = [];

    /** @var list<string> */
    public $translatable = ['name', 'description', 'slug'];

    /** @return list<string> */
    protected function translationStatusExcludes(): array
    {
        return ['slug'];
    }
}
