<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Translatable\Concerns\HasTranslatableSlug;

/**
 * A plain, non-translatable slug model — exercises the single-string slug branch.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 */
final class Article extends Model
{
    use HasTranslatableSlug;
    use SoftDeletes;

    protected $table = 'articles';

    protected $guarded = [];
}
