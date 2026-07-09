<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

/**
 * A translatable model with a non-`id` (string) primary key — exercises slug-uniqueness
 * robustness against custom key names.
 *
 * @property string $code
 */
final class KeyedRecord extends Model implements Translatable
{
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'keyed_records';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    /** @var list<string> */
    public $translatable = ['slug'];
}
