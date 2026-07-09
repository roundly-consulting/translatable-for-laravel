<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Translatable\Concerns\DispatchesTranslationEvents;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

/**
 * A translatable model that opts into the TranslationsChanged event.
 *
 * @property int $id
 */
final class EventfulTopic extends Model implements Translatable
{
    use DispatchesTranslationEvents;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'topics';

    protected $guarded = [];

    /** @var list<string> */
    public $translatable = ['name', 'description', 'slug'];
}
