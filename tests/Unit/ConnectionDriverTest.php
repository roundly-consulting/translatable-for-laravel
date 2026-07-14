<?php

declare(strict_types=1);

use Illuminate\Database\SQLiteConnection;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Translatable\Support\ConnectionDriver;

function connectionWithDriver(string $driver): SQLiteConnection
{
    return new SQLiteConnection(new PDO('sqlite::memory:'), 'db', '', ['driver' => $driver]);
}

it('recognises postgres', function (): void {
    expect(ConnectionDriver::isPgsql(connectionWithDriver('pgsql')))->toBeTrue();
});

it('answers false for every other driver the toolkit models', function (string $driver): void {
    expect(ConnectionDriver::isPgsql(connectionWithDriver($driver)))->toBeFalse();
})->with(['mysql', 'mariadb', 'sqlite']);

/**
 * The contract that makes it safe to build on the toolkit's enum: it models four drivers, and
 * `sqlsrv` — a first-party Laravel driver — is not one of them. `DatabaseDriver::current()`
 * THROWS on an unmodelled driver, so this package must never call it: a search request on SQL
 * Server has to take the portable `like` branch, not blow up. `tryFrom()` is what guarantees
 * that, and this pin fails the moment anyone swaps it for `current()`.
 */
it('answers false — never throws — for a driver the toolkit does not model', function (string $driver): void {
    expect(DatabaseDriver::tryFrom($driver))->toBeNull()
        ->and(ConnectionDriver::isPgsql(connectionWithDriver($driver)))->toBeFalse();
})->with(['sqlsrv', 'oracle', 'firebird', '']);
