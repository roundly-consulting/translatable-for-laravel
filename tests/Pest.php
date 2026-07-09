<?php

declare(strict_types=1);

use RoundlyConsulting\Translatable\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature');

/**
 * Whether a PostgreSQL test database is configured (the pgsql lane runs only then).
 */
function pgsqlConfigured(): bool
{
    return env('TRANSLATABLE_PGSQL_DATABASE') !== null;
}
