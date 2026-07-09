<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Translatable\Concerns\HasTranslatableSlug;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

/**
 * A Topic that opts into the primary-key route-binding fallback.
 *
 * @property int $id
 */
final class IdResolvableTopic extends Model implements Translatable
{
    use HasTranslatableSlug;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'topics';

    protected $guarded = [];

    /** @var list<string> */
    public $translatable = ['name', 'description', 'slug'];

    protected bool $resolveSlugBindingById = true;
}
