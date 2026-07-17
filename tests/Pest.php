<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\Tests\TestCase;

// ArchTest.php is listed explicitly because an arch file is not automatically test-cased,
// and  registers an it() case that needs one.
uses(TestCase::class)->in('ArchTest.php', 'Unit', 'Feature');

/**
 * Whether a real PostgreSQL server is reachable — the gate for every case that asserts a
 * Postgres-only behaviour (the functional per-locale unique indexes, the `ilike` path).
 *
 * This used to read `env('TRANSLATABLE_PGSQL_DATABASE') !== null`, a convention local to
 * this package. Two things were wrong with that, and both are why the fleet standardised:
 *
 *  1. it asked whether the vars were SET, not whether an engine could actually be REACHED —
 *     so a typo'd host, a dead service, or a wrong password all read as "configured" and the
 *     lane would fail rather than skip, while an unreachable engine that nobody had declared
 *     read as "not configured" and skipped silently forever;
 *  2. three packages invented three incompatible conventions for the same job.
 *
 * `connectionAvailable()` ships on PackageTestCase and really opens the connection, so a run
 * with no Postgres skips *visibly* with a stated reason, and a misconfigured CI leg cannot
 * report green having asserted nothing.
 */
function pgsqlConfigured(): bool
{
    return test()->connectionAvailable('pgsql');
}
