<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Translatable\Contracts\Translatable;
use RoundlyConsulting\Translatable\DataTransferObjects\SlugOptions;
use RoundlyConsulting\Translatable\Support\SlugGenerator;
use RoundlyConsulting\Translatable\Support\Translations;

/**
 * Auto-generates slugs on create — per-locale for a translatable slug column, or a single
 * string for a plain slug column. Admin-supplied slugs are preserved; collisions suffix
 * (-2, -3, …); a missing source produces a short random slug.
 *
 * @phpstan-require-extends Model
 */
trait HasTranslatableSlug
{
    public static function bootHasTranslatableSlug(): void
    {
        static::creating(static function (Model $model): void {
            /** @var self $model */
            $model->generateSlugs();
        });
    }

    public function slugColumn(): string
    {
        return 'slug';
    }

    public function slugSourceField(): string
    {
        return SlugOptions::fromConfig()->sourceField;
    }

    public function slugOptions(): SlugOptions
    {
        return SlugOptions::fromConfig();
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWhereLocaleSlug(Builder $query, string $slug, ?string $locale = null): Builder
    {
        $column = $this->slugColumn();
        $locale ??= app()->getLocale();
        $fallback = (string) config('translatable.fallback_locale', 'en');

        return $query->where(function (Builder $inner) use ($column, $slug, $locale, $fallback): void {
            $inner->where("{$column}->{$locale}", $slug);

            if ($fallback !== $locale) {
                $inner->orWhere("{$column}->{$fallback}", $slug);
            }
        });
    }

    /**
     * Rescue a slug from any supported locale (e.g. a stale-locale URL where the
     * request locale differs from the locale the slug was minted in).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWhereAnySlug(Builder $query, string $slug): Builder
    {
        $column = $this->slugColumn();

        return $query->where(function (Builder $inner) use ($column, $slug): void {
            foreach (Translations::supported() as $locale) {
                $inner->orWhere("{$column}->{$locale}", $slug);
            }
        });
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null && $field !== $this->slugColumn()) {
            return parent::resolveRouteBinding($value, $field);
        }

        if (is_numeric($value)) {
            $byId = $this->newQuery()->whereKey($value)->first();

            if ($byId !== null) {
                return $byId;
            }
        }

        $slug = (string) $value;

        return $this->newQuery()->whereLocaleSlug($slug)->first()
            ?? $this->newQuery()->whereAnySlug($slug)->first();
    }

    protected function generateSlugs(): void
    {
        $column = $this->slugColumn();

        if (! ($this instanceof Translatable && $this->isTranslatableAttribute($column))) {
            $this->generateSingleSlug($column);

            return;
        }

        $generator = new SlugGenerator($this->slugOptions());
        $sources = $this->getTranslations($this->slugSourceField());

        $slugs = [];

        foreach ($this->getTranslations($column) as $locale => $value) {
            if (is_string($value) && $value !== '') {
                $slugs[(string) $locale] = $value;
            }
        }

        foreach ($sources as $locale => $source) {
            $locale = (string) $locale;

            if (isset($slugs[$locale])) {
                continue;
            }

            $slugs[$locale] = $generator->generate(
                is_string($source) ? $source : null,
                fn (string $candidate): bool => $this->translatedSlugExists($column, $locale, $candidate),
            );
        }

        $this->setTranslations($column, $slugs);
    }

    protected function generateSingleSlug(string $column): void
    {
        $current = $this->getAttribute($column);

        if (is_string($current) && $current !== '') {
            return;
        }

        $generator = new SlugGenerator($this->slugOptions());
        $source = $this->getAttribute($this->slugSourceField());

        $this->setAttribute($column, $generator->generate(
            is_string($source) ? $source : null,
            fn (string $candidate): bool => $this->singleSlugExists($column, $candidate),
        ));
    }

    protected function translatedSlugExists(string $column, string $locale, string $candidate): bool
    {
        return $this->newQuery()
            ->where("{$column}->{$locale}", $candidate)
            ->when($this->exists, fn (Builder $query): Builder => $query->whereKeyNot($this->getKey()))
            ->exists();
    }

    protected function singleSlugExists(string $column, string $candidate): bool
    {
        return $this->newQuery()
            ->where($column, $candidate)
            ->when($this->exists, fn (Builder $query): Builder => $query->whereKeyNot($this->getKey()))
            ->exists();
    }
}
