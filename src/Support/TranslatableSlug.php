<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Validator;
use RoundlyConsulting\Translatable\Contracts\SupportedLocales;
use RoundlyConsulting\Translatable\DataTransferObjects\UniqueSlugContext;

/**
 * Migration/index helpers for per-locale translatable slugs, plus per-locale
 * uniqueness validation. The functional unique indexes are Postgres-only.
 */
final class TranslatableSlug
{
    /**
     * A jsonb slug column (json on non-pgsql; Postgres maps json to jsonb).
     */
    public static function column(Blueprint $table, string $name = 'slug'): ColumnDefinition
    {
        return $table->jsonb($name);
    }

    /**
     * Create one functional unique index per supported locale (pgsql only, idempotent).
     * NULL (missing locale) is exempt, so partial translations stay legal.
     */
    public static function uniqueIndexes(string $table, string $column = 'slug'): void
    {
        // Everything below reaches a raw DDL statement where nothing can be parameter-bound,
        // so identifiers are allowlisted here (before the driver short-circuit) and the locale
        // is PDO-quoted below.
        LocaleGuard::ensureIdentifier($table, 'table name');
        LocaleGuard::ensureIdentifier($column, 'column name');

        $connection = Schema::getConnection();

        if (! ConnectionDriver::isPgsql($connection)) {
            return;
        }

        $grammar = $connection->getSchemaGrammar();
        $pdo = $connection->getPdo();

        foreach (self::locales() as $locale) {
            LocaleGuard::ensure($locale);

            $index = self::indexName($table, $column, $locale);

            $wrappedIndex = $grammar->wrap(LocaleGuard::ensureIdentifier($index, 'index name'));
            $wrappedTable = $grammar->wrap($table);
            $wrappedColumn = $grammar->wrap($column);
            $quotedLocale = $pdo->quote($locale);

            $connection->statement(
                "CREATE UNIQUE INDEX IF NOT EXISTS {$wrappedIndex} ON {$wrappedTable} (({$wrappedColumn}->>{$quotedLocale}))"
            );
        }
    }

    /**
     * Assert that every supplied per-locale slug is unique, adding slug.<locale> errors.
     */
    public static function assertUnique(Validator $validator, UniqueSlugContext $context): void
    {
        foreach (Translations::fromInput($context->input) as $locale => $slug) {
            if (self::slugTaken($context->table, $context->column, $locale, $slug, $context->ignoreId, $context->keyName)) {
                $validator->errors()->add(
                    "{$context->column}.{$locale}",
                    (string) __('translatable::validation.unique_slug', ['locale' => $locale]),
                );
            }
        }
    }

    /**
     * Whether the given slug is already used for the given locale (ignoring a row by its key and
     * soft-deleted rows). `$keyName` supports non-`id` primary keys (uuid, custom, …).
     */
    public static function slugTaken(string $table, string $column, string $locale, string $slug, int|string|null $ignoreId, string $keyName = 'id'): bool
    {
        LocaleGuard::ensureIdentifier($column, 'column name');
        LocaleGuard::ensure($locale);

        $query = DB::table($table)->where("{$column}->{$locale}", $slug);

        if ($ignoreId !== null) {
            $query->where($keyName, '!=', $ignoreId);
        }

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }

    public static function indexName(string $table, string $column, string $locale): string
    {
        return "{$table}_{$column}_{$locale}_unique";
    }

    /**
     * @return list<string>
     */
    private static function locales(): array
    {
        return app(SupportedLocales::class)->supported();
    }
}
