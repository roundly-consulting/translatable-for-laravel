<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use RoundlyConsulting\Translatable\Contracts\Translatable;
use RoundlyConsulting\Translatable\DataTransferObjects\SlugOptions;
use RoundlyConsulting\Translatable\Support\LocaleGuard;
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
        $locale = LocaleGuard::ensure($locale ?? app()->getLocale());
        $fallback = LocaleGuard::ensure((string) config('translatable.fallback_locale', 'en'));

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
                $inner->orWhere("{$column}->".LocaleGuard::ensure($locale), $slug);
            }
        });
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null && $field !== $this->slugColumn()) {
            return parent::resolveRouteBinding($value, $field);
        }

        $slug = (string) $value;

        $bySlug = $this->newQuery()->whereLocaleSlug($slug)->first()
            ?? $this->newQuery()->whereAnySlug($slug)->first();

        if ($bySlug !== null) {
            return $bySlug;
        }

        // Opt-in id fallback: only when explicitly enabled, and only after slug misses — so
        // numeric slugs aren't shadowed by same-numbered ids and slug routes can't be enumerated.
        if ($this->resolvesSlugBindingById() && is_numeric($value)) {
            return $this->newQuery()->whereKey($value)->first();
        }

        return null;
    }

    /**
     * Whether to fall back to a primary-key lookup on a slug route when the slug misses.
     * Off unless the model declares `protected bool $resolveSlugBindingById = true;`.
     */
    protected function resolvesSlugBindingById(): bool
    {
        return property_exists($this, 'resolveSlugBindingById') && $this->resolveSlugBindingById === true;
    }

    /**
     * How many times a create is retried after a unique-slug violation before giving up.
     * Override with `protected int $slugInsertRetries = N;` on the model.
     */
    protected function slugInsertRetries(): int
    {
        if (property_exists($this, 'slugInsertRetries') && is_int($this->slugInsertRetries)) {
            return $this->slugInsertRetries;
        }

        return 5;
    }

    /**
     * Retry the insert on a unique-slug violation (a TOCTOU race where another writer
     * committed the same slug between generation and insert), bumping any colliding slug to
     * the next available suffix each time. Bounded by `$slugInsertRetries`.
     *
     * @param  Builder<static>  $query
     */
    protected function performInsert(Builder $query): bool
    {
        $attempts = 0;

        while (true) {
            try {
                return parent::performInsert($query);
            } catch (QueryException $exception) {
                $attempts++;

                if ($attempts > $this->slugInsertRetries() || ! $this->isSlugUniqueViolation($exception)) {
                    throw $exception;
                }

                $this->bumpSlugsAfterCollision();
            }
        }
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
            if ($value !== '') {
                $slugs[$locale] = $value;
            }
        }

        foreach ($sources as $locale => $source) {
            if (isset($slugs[$locale])) {
                continue;
            }

            $slugs[$locale] = $generator->generate(
                $source === '' ? null : $source,
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
        $locale = LocaleGuard::ensure($locale);

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

    /**
     * Re-suffix any slug that now collides in the database, so the next insert attempt can win.
     * Re-checks against live data each retry, so a run of concurrent creates each settles on a
     * distinct suffix.
     */
    private function bumpSlugsAfterCollision(): void
    {
        $column = $this->slugColumn();
        $generator = new SlugGenerator($this->slugOptions());

        if ($this instanceof Translatable && $this->isTranslatableAttribute($column)) {
            $map = $this->getTranslations($column);

            foreach ($map as $locale => $slug) {
                if ($this->translatedSlugExists($column, $locale, $slug)) {
                    $map[$locale] = $generator->generate(
                        $slug,
                        fn (string $candidate): bool => $this->translatedSlugExists($column, $locale, $candidate),
                    );
                }
            }

            $this->setTranslations($column, $map);

            return;
        }

        $current = $this->getAttribute($column);

        if (is_string($current) && $this->singleSlugExists($column, $current)) {
            $this->setAttribute($column, $generator->generate(
                $current,
                fn (string $candidate): bool => $this->singleSlugExists($column, $candidate),
            ));
        }
    }

    private function isSlugUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $isUnique = in_array($sqlState, ['23505', '23000'], true)
            || str_contains($exception->getMessage(), 'UNIQUE constraint failed');

        // Only retry violations that mention the slug column/index — never mask another
        // table's unique constraint into an endless bump loop.
        return $isUnique && str_contains($exception->getMessage(), $this->slugColumn());
    }
}
