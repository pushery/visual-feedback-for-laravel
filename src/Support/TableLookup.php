<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\Support\Facades\DB;

/**
 * Whether a table exists where a query on the default connection finds it.
 *
 * `Schema::hasTable()` looks for an unqualified table in PostgreSQL's `current_schema()`, the
 * first schema of the `search_path` that exists, while a query finds the table anywhere along the
 * `search_path`. With `search_path` set to `tenant, public` and the reports table in `public`,
 * `hasTable()` answers false for a table every query reads and writes, and the package would take
 * its store for absent: the database channel would skip the write, and the commands that prune,
 * forget and sweep would act as if nothing were stored.
 *
 * On PostgreSQL the name is resolved with `to_regclass()`, which walks the `search_path` as a
 * query does, quoted and prefixed as the query builder writes it. Every other driver keeps
 * `hasTable()`, whose answer already matches its queries.
 */
final class TableLookup
{
    public static function exists(string $table): bool
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            return $connection->getSchemaBuilder()->hasTable($table);
        }

        return $connection->scalar('select to_regclass(?) is not null', [$connection->getQueryGrammar()->wrapTable($table)]) === true;
    }
}
