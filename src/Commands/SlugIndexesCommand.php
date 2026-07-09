<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
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
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->warn('translatable:slug-indexes only runs on PostgreSQL; skipping.');

            return self::SUCCESS;
        }

        $table = is_string($argument = $this->argument('table')) ? $argument : '';
        $column = is_string($option = $this->option('column')) ? $option : 'slug';

        TranslatableSlug::uniqueIndexes($table, $column);

        $this->info("Ensured per-locale unique slug indexes on {$table}.{$column}.");

        return self::SUCCESS;
    }
}
