<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * The database drivers this package special-cases. Postgres is the only driver that
 * supports the functional per-locale unique slug indexes and native `ilike` search.
 */
enum DatabaseDriver: string
{
    use Helpers;

    case Mysql = 'mysql';

    case Mariadb = 'mariadb';

    case Pgsql = 'pgsql';

    case Sqlite = 'sqlite';

    case Sqlsrv = 'sqlsrv';
}
