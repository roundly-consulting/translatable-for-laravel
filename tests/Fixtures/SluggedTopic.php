<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Sluggable\Concerns\HasSlug;
use RoundlyConsulting\Sluggable\Contracts\Sluggable;
use RoundlyConsulting\Sluggable\Definitions\SlugDefinition;
use RoundlyConsulting\Sluggable\Definitions\SlugOptions;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;
use RoundlyConsulting\Translatable\Contracts\Translatable;

/**
 * The README integration, verbatim: two traits, two interfaces, no cast. It is also the
 * trait-collision guard — if HasSlug ever declared a method HasTranslations also declares,
 * this class would fail to compile and every case using it would fatal.
 *
 * @property int $id
 */
final class SluggedTopic extends Model implements Sluggable, Translatable
{
    use HasSlug;
    use HasTranslations;

    protected $table = 'topics';

    protected $guarded = [];

    /** @var list<string> */
    public $translatable = ['name', 'description', 'slug'];

    public function slugOptions(): SlugOptions
    {
        return SlugOptions::make(
            SlugDefinition::for('slug')->from('name')->routeKey()->bindByKeyFallback(false),
        );
    }

    /** @return list<string> */
    protected function translationStatusExcludes(): array
    {
        return ['slug'];
    }
}
