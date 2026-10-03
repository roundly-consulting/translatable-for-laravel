<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\PackageToolkit\Support\LikeEscaper;
use RoundlyConsulting\PackageToolkit\Support\RawExpression;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\Contracts\Translatable;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationChanges;
use RoundlyConsulting\Translatable\DataTransferObjects\TranslationSearch;
use RoundlyConsulting\Translatable\Enums\FallbackMode;
use RoundlyConsulting\Translatable\Exceptions\InvalidLocaleException;

/**
 * The single, injectable front door for the locale-map toolkit, bound as a singleton and
 * fronted by the `Translatable` facade. The `HasTranslations` trait reads its locale set,
 * fallback settings, locale validation and map resolution through here too, so a host that
 * swaps the manager is observed on the facade and on every model alike.
 *
 * Intentionally NOT `final`: this is the package's one designed extension/swap seam. Hosts
 * override a method (e.g. `supported()`) by extending it and `Translatable::swap()`-ing the
 * subclass — the sibling methods observe the override polymorphically. See FacadeTest.
 *
 * There are no actions and no fake: everything here is pure locale-map computation over config
 * and the bound `SupportedLocales` (no DB write, queue, mail, event or HTTP call). Pin the
 * locale set in a test with `config()->set('translatable.locales', [...])` or by binding
 * `SupportedLocales`.
 */
class TranslationManager
{
    /**
     * The supported locales, from the bound SupportedLocales source of truth.
     *
     * @return list<string>
     */
    public function supported(): array
    {
        return app(SupportedLocales::class)->supported();
    }

    /**
     * Whether a locale is in the supported set. Exact, case-sensitive match — the same rule
     * `fromInput()` and `strict_locales` apply.
     */
    public function isSupported(string $locale): bool
    {
        return in_array($locale, $this->supported(), true);
    }

    public function currentLocale(): string
    {
        return app()->getLocale();
    }

    /**
     * Validate a locale key and return it. A malformed key (quotes, spaces, markup, SQL) always
     * throws; in strict mode a well-formed key outside the supported set throws too.
     *
     * @throws InvalidLocaleException
     */
    public function ensureLocale(string $locale, bool $strict = false): string
    {
        return LocaleGuard::ensure($locale, $strict, $strict ? $this->supported() : null);
    }

    /**
     * Resolve a raw locale map to one value through the fallback chain: the requested locale
     * (default: the current one), then — per the mode — the fallback locale, then the first
     * non-blank value in supported-locale order (then the other locales alphabetically). Mode
     * and fallback locale default to the configured ones. Blank values never win: null, '',
     * booleans, arrays and objects are skipped, ints and floats are cast to strings.
     *
     * @param  array<array-key, mixed>  $map
     */
    public function resolve(array $map, ?string $locale = null, ?FallbackMode $mode = null, ?string $fallbackLocale = null): ?string
    {
        $mode ??= $this->fallbackMode();

        return (new FallbackResolver)->resolve(
            $map,
            $locale ?? $this->currentLocale(),
            $fallbackLocale ?? $this->fallbackLocale(),
            $mode,
            $mode === FallbackMode::Any ? $this->supported() : [],
        );
    }

    /**
     * Normalise arbitrary input into a locale map:
     * a bare string becomes the current locale; blank + unsupported locales are dropped —
     * including the current locale a bare string maps to, so the result only ever carries
     * supported locales (and `strict_locales` accepts it).
     *
     * @return array<string, string>
     */
    public function fromInput(mixed $input): array
    {
        if (is_string($input)) {
            $locale = $this->currentLocale();

            return $input === '' || ! $this->isSupported($locale) ? [] : [$locale => $input];
        }

        if (! is_array($input)) {
            return [];
        }

        $supported = $this->supported();
        $map = [];

        foreach ($input as $locale => $value) {
            $locale = (string) $locale;

            if (! in_array($locale, $supported, true)) {
                continue;
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            $map[$locale] = $value;
        }

        return $map;
    }

    /**
     * The effective global fallback mode from config: `Any` when not set (absent, null or
     * blank), and a throw for a value that names no mode — a typo must not silently widen
     * the chain to `Any`.
     */
    public function fallbackMode(): FallbackMode
    {
        return Config::enum('translatable.fallback', FallbackMode::class, FallbackMode::Any);
    }

    /**
     * The global fallback locale. Null or blank (`''`, whitespace — a host's `KEY=`) is not
     * set, and not set means "no fallback locale" (documented), returned as `''`; anything
     * else must be a well-formed locale key, or it throws instead of being cast and then
     * silently never matching.
     */
    public function fallbackLocale(): string
    {
        $locale = config('translatable.fallback_locale', 'en') ?? '';

        if (is_string($locale) && trim($locale) === '') {
            return '';
        }

        if (! is_string($locale) || ! LocaleGuard::isValid($locale)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration value [translatable.fallback_locale] must be a locale key such as `en` (or not set for none), [%s] given.',
                is_scalar($locale) ? var_export($locale, true) : get_debug_type($locale),
            ));
        }

        return $locale;
    }

    /**
     * Validation rules for a locale-map field: the field itself plus each per-locale value.
     * When required, at least one locale must be filled. Extra `$each` rules (e.g. `max:120`)
     * are appended to every per-locale value.
     *
     * @param  array<int, mixed>  $each
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $field, bool $required, array $each = []): array
    {
        $rules = [
            $field => $required
                ? array_merge(['required', 'array'], $this->filledRule($field))
                : ['sometimes', 'array'],
        ];

        foreach ($this->supported() as $locale) {
            $rules["{$field}.{$locale}"] = array_merge(['nullable', 'string'], $each);
        }

        return $rules;
    }

    /**
     * A rule set enforcing that at least one locale of the field is non-blank. `rules()` adds it
     * to every required field; use it directly to compose your own rule list.
     *
     * @return list<Closure(string, mixed, Closure): void>
     */
    public function filledRule(string $field): array
    {
        return [
            static function (string $attribute, mixed $value, Closure $fail): void {
                $filled = is_array($value)
                    ? array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== '')
                    : [];

                if ($filled === []) {
                    $fail((string) __('translatable::validation.filled'));
                }
            },
        ];
    }

    /**
     * PATCH-merge a set of changes onto a model: only supplied locales are touched.
     */
    public function apply(Translatable $model, TranslationChanges $changes): void
    {
        foreach ($changes->fields as $field => $localeMap) {
            foreach ($localeMap as $locale => $value) {
                $model->setTranslation($field, (string) $locale, $value);
            }
        }
    }

    /**
     * Add a per-locale, case-insensitive search across the given fields. A search with no
     * fields (or no supported locale) searches nothing, so it matches no rows — an empty OR
     * group would otherwise add no constraint and return every row.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function search(Builder $query, TranslationSearch $search): Builder
    {
        foreach ($search->fields as $field) {
            LocaleGuard::ensureIdentifier($field, 'search field');
        }

        $locales = $this->supported();

        if ($search->fields === [] || $locales === []) {
            return $query->whereRaw('1 = 0');
        }

        $isPgsql = ConnectionDriver::isPgsql($query->getModel()->getConnection());
        // Escape LIKE wildcards so a term like "100%" scopes the (bound) match instead of
        // matching everything; the value stays bound, this only neutralises %/_/\ meaning.
        $term = '%'.LikeEscaper::escape($search->term).'%';
        $grammar = $query->getQuery()->getGrammar();

        return $query->where(static function (Builder $inner) use ($search, $isPgsql, $term, $locales, $grammar): void {
            foreach ($search->fields as $field) {
                foreach ($locales as $locale) {
                    // Only the grammar-wrapped, allowlisted identifier reaches the SQL; the
                    // needle and the escape character are bound.
                    $column = $grammar->wrap("{$field}->".LocaleGuard::ensure($locale));

                    // Two driver-specific requirements, both of which are invisible on the
                    // engine that does not need them:
                    //
                    // 1. The escape character MUST be stated explicitly. SQLite (and SQL
                    //    Server) have no default LIKE escape character, so without it the
                    //    escaping backslashes above stay literal and the term matches
                    //    nothing. Green on postgres and mysql, zero rows on sqlite — this
                    //    package shipped exactly that (#39).
                    //
                    // 2. Case-insensitivity has to be MADE to happen off postgres. `ilike`
                    //    is case-insensitive by definition; plain `like` is not, and whether
                    //    it behaves that way is a property of the COLLATION, not of LIKE.
                    //    SQLite's like is ASCII-case-insensitive by default, so it flattered
                    //    this code for the package's whole life — but MySQL extracts JSON as
                    //    utf8mb4_bin, which is case-SENSITIVE, so a documented
                    //    case-insensitive search returned ZERO rows there. Lowering both
                    //    sides states the intent in the SQL rather than inheriting it from
                    //    whichever engine happens to be underneath.
                    $expression = $isPgsql
                        ? "{$column} ilike ? escape ?"
                        : "lower({$column}) like lower(?) escape ?";

                    $inner->whereRaw(new RawExpression($expression), [$term, '\\'], 'or');
                }
            }
        });
    }

    /**
     * Run a callback with the app locale temporarily set to `$locale`, then restore it —
     * a stateless, leak-free way to read a model in another locale (queued renders, sitemaps).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function usingLocale(string $locale, Closure $callback): mixed
    {
        $previous = $this->currentLocale();
        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }
}
