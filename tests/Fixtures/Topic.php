<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

/**
 * @property int $id
 */
final class Topic extends Model implements Translatable
{
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'topics';

    protected $guarded = [];

    /** @var list<string> */
    public $translatable = ['name', 'description', 'slug'];
}
