<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Sluggable\Concerns\HasSlug;
use RoundlyConsulting\Sluggable\Contracts\Sluggable;
use RoundlyConsulting\Sluggable\Definitions\SlugDefinition;
use RoundlyConsulting\Sluggable\Definitions\SlugOptions;
use RoundlyConsulting\Translatable\Concerns\HasTranslations;

/**
 * The documented mistake: HasTranslations without `implements Translatable`. With no cast,
 * sluggable would otherwise read the jsonb `slug` as a string column — it refuses instead.
 */
final class UncontractedSluggedTopic extends Model implements Sluggable
{
    use HasSlug;
    use HasTranslations;

    protected $table = 'topics';

    protected $guarded = [];

    /** @var list<string> */
    public $translatable = ['name', 'description', 'slug'];

    public function slugOptions(): SlugOptions
    {
        return SlugOptions::make(SlugDefinition::for('slug')->from('name'));
    }
}
