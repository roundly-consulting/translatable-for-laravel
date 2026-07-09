<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Translatable\Enums\DatabaseDriver;
use RoundlyConsulting\Translatable\Support\LocaleGuard;
use RoundlyConsulting\Translatable\Support\TranslatableSlug;

/**
 * (Re)creates the per-locale unique slug indexes for a table. Run once after
 * adding a supported locale. Create-only; pgsql-only (warns and no-ops elsewhere).
 */
final class SlugIndexesCommand extends Command
{
    protected $signature = 'translatable:slug-indexes {table} {--column=slug}';

    protected $description = 'Create missing per-locale unique slug indexes for a translatable table (PostgreSQL only)';

    public function handle(): int
    {
        $table = is_string($argument = $this->argument('table')) ? $argument : '';
        $column = is_string($option = $this->option('column')) ? $option : 'slug';

        if (! LocaleGuard::isValidIdentifier($table)) {
            $this->error("Invalid table name [{$table}]; only letters, digits and underscores are allowed.");

            return self::FAILURE;
        }

        if (! LocaleGuard::isValidIdentifier($column)) {
            $this->error("Invalid column name [{$column}]; only letters, digits and underscores are allowed.");

            return self::FAILURE;
        }

        if (Schema::getConnection()->getDriverName() !== DatabaseDriver::Pgsql->value) {
            $this->warn('translatable:slug-indexes only runs on PostgreSQL; skipping.');

            return self::SUCCESS;
        }

        TranslatableSlug::uniqueIndexes($table, $column);

        $this->info("Ensured per-locale unique slug indexes on {$table}.{$column}.");

        return self::SUCCESS;
    }
}
