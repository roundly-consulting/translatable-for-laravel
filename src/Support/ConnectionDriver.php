<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Support;

use Illuminate\Database\Connection;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;

/**
 * Driver discrimination over the toolkit's `DatabaseDriver` enum. Postgres is the only driver
 * this package special-cases (native `ilike`, functional per-locale unique slug indexes);
 * every other driver takes the portable path.
 *
 * Deliberately uses `tryFrom()` rather than the toolkit's `DatabaseDriver::current()`: that
 * helper THROWS for a driver the enum does not model, and the enum models four of Laravel's
 * five first-party drivers — `sqlsrv` has no case. Every question this package asks is "is
 * this Postgres?", so an unmodelled driver must answer *no* and fall through to the portable
 * branch. A search request (or a slug-index run) on SQL Server must not blow up because the
 * toolkit's enum is missing a case.
 */
final class ConnectionDriver
{
    public static function isPgsql(Connection $connection): bool
    {
        return DatabaseDriver::tryFrom($connection->getDriverName())?->isPgsql() ?? false;
    }
}
