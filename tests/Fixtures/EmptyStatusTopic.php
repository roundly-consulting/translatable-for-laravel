<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

/**
 * A translatable model whose only translatable attribute is excluded from status, so the
 * whole-model status has no fields to weigh (vacuously complete).
 *
 * @property int $id
 */
final class EmptyStatusTopic extends Model implements Translatable
{
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'topics';

    protected $guarded = [];

    /** @var list<string> */
    public $translatable = ['slug'];

    /** @return list<string> */
    protected function translationStatusExcludes(): array
    {
        return ['slug'];
    }
}
