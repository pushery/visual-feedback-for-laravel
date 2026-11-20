<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionResolverInterface as ConnectionResolver;
use Illuminate\Support\Carbon;

/**
 * Deletes the expired entries this package keeps in a database cache store.
 *
 * The database store deletes an expired entry only when its key is read again, and most keys of
 * this package are written for one report or one visitor and not read again once that has passed:
 * the delivery receipts and the claims that make each channel settle once, the erasure records of
 * `visual-feedback:forget`, and the counters of the rate limits. On that store each one stays a row
 * of the cache table for good. Scheduled once a day, this deletes them, a thousand rows to a
 * statement:
 *
 *     Schedule::call(PruneExpiredCacheKeys::class)->daily();
 *
 * It prunes the default store, which holds the delivery bookkeeping and the erasure records, and
 * the store of the rate limiter, `cache.limiter` or else the default store, wherever either is a
 * database store, and does nothing on a store of any other driver. Only keys under
 * `visual-feedback:` go, and only once they have expired, when the store already treats them as
 * missing.
 */
final readonly class PruneExpiredCacheKeys
{
    /** The most rows one statement deletes, so a large table is pruned in short statements. */
    private const int CHUNK = 1000;

    public function __construct(
        private Config $config,
        private ConnectionResolver $connections,
    ) {}

    /** Delete every expired entry of this package, and return how many rows went. */
    public function __invoke(): int
    {
        $now = Carbon::now()->getTimestamp();
        $deleted = 0;

        foreach ($this->tables() as $table) {
            $connection = $this->connections->connection($table['connection']);
            $pattern = $this->keysLike($table['prefix']);

            do {
                $chunk = $connection->table($table['table'])
                    ->where('key', 'like', $pattern)
                    ->where('expiration', '<=', $now)
                    ->limit(self::CHUNK)
                    ->delete();

                $deleted += $chunk;
            } while ($chunk === self::CHUNK);
        }

        return $deleted;
    }

    /**
     * The tables to prune: one for each database store among the default store and the store of
     * the rate limiter, and a single one where both write to the same table under the same prefix.
     *
     * @return list<array{connection: ?string, table: string, prefix: string}>
     */
    private function tables(): array
    {
        $default = $this->config->get('cache.default');
        $limiter = $this->config->get('cache.limiter');
        $tables = [];

        foreach ([$default, is_string($limiter) && $limiter !== '' ? $limiter : $default] as $name) {
            $table = is_string($name) && $name !== '' ? $this->tableOf($name) : null;

            if ($table !== null) {
                $tables[($table['connection'] ?? '')."\0".$table['table']."\0".$table['prefix']] = $table;
            }
        }

        return array_values($tables);
    }

    /**
     * The table, connection and key prefix of a database store, as Laravel builds the store, or
     * null for a store of another driver, a store nobody configured and one without a table.
     *
     * @return array{connection: ?string, table: string, prefix: string}|null
     */
    private function tableOf(string $name): ?array
    {
        $stores = $this->config->get('cache.stores');
        $store = is_array($stores) ? ($stores[$name] ?? null) : null;

        if (! is_array($store) || ($store['driver'] ?? null) !== 'database') {
            return null;
        }

        $table = $store['table'] ?? null;
        $connection = $store['connection'] ?? null;
        // As Laravel builds the store: its own prefix, or the one every store shares.
        $prefix = $store['prefix'] ?? $this->config->get('cache.prefix');

        if (! is_string($table) || $table === '') {
            return null;
        }

        return [
            'connection' => is_string($connection) && $connection !== '' ? $connection : null,
            'table' => $table,
            'prefix' => is_string($prefix) ? $prefix : '',
        ];
    }

    /**
     * The keys of this package as a LIKE pattern.
     *
     * A character of the prefix that LIKE reads as more than itself on one of the engines, `%`, `\`
     * and `[`, stands as `_`, any one character, so the pattern means the same on every engine
     * without an escape clause. It can then match a key beside these, and so can a LIKE that ignores
     * case, as SQLite's does and MySQL's under its default collation. Such a key goes only once it
     * has expired, and the store already treats it as missing then.
     */
    private function keysLike(string $prefix): string
    {
        return str_replace(['%', '\\', '['], '_', $prefix).'visual-feedback:%';
    }
}
