<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Translatable\Contracts\Translatable;
use RoundlyConsulting\Translatable\Events\TranslationsChanged;

/**
 * Opt-in companion to HasTranslations: dispatches a single TranslationsChanged event once per
 * persisted save when any translatable attribute changed. The base trait stays side-effect-free;
 * a model adds this trait only when it wants the hook.
 *
 * @phpstan-require-extends Model
 */
trait DispatchesTranslationEvents
{
    public static function bootDispatchesTranslationEvents(): void
    {
        // On create the change set is the non-empty maps; on update it is the dirty attributes.
        static::created(static function (Model $model): void {
            self::fireTranslationsChanged($model, true);
        });

        static::updated(static function (Model $model): void {
            self::fireTranslationsChanged($model, false);
        });
    }

    private static function fireTranslationsChanged(Model $model, bool $created): void
    {
        if (! $model instanceof Translatable) {
            return;
        }

        $changed = [];

        foreach ($model->getTranslatableAttributes() as $attribute) {
            $isChanged = $created
                ? $model->getTranslations($attribute) !== []
                : $model->wasChanged($attribute);

            if ($isChanged) {
                $changed[] = $attribute;
            }
        }

        if ($changed !== []) {
            event(new TranslationsChanged($model, $changed));
        }
    }
}
