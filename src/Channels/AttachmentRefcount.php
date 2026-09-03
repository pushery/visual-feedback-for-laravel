<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The per-report attachment refcount, backed by the CACHE — the optional
 * reports table cannot carry it (a mail-only, DB-less consumer still needs cleanup). It is
 * ARMED once with the number of transient channels that will deliver the report, and each
 * channel DECREMENTS it exactly once when it terminally settles; the file cleanup runs when
 * the count reaches zero. The decrement is atomic (Cache::decrement) because two channel jobs
 * finishing on different workers at the same instant is the normal case, not an edge.
 *
 * The counter is only ever ARMED for the all-transient case (see ReportDeliveryTracker); a
 * missing key therefore means "no refcount cleanup applies" — a persistent holder is present,
 * or the counter was already released. Its TTL comfortably outlives the longest queue retry
 * horizon; the age-based orphan sweep is the named backstop for a cache eviction.
 */
final readonly class AttachmentRefcount
{
    /** A generous week — far beyond any channel's retry/backoff horizon (orphan sweep backstops eviction). */
    private const int TTL_SECONDS = 7 * 24 * 60 * 60;

    public function __construct(private Cache $cache) {}

    public function arm(string $reportId, int $count): void
    {
        $this->cache->put($this->key($reportId), $count, self::TTL_SECONDS);
    }

    public function isArmed(string $reportId): bool
    {
        return $this->cache->has($this->key($reportId));
    }

    /** Atomic terminal decrement; returns the remaining count. */
    public function decrement(string $reportId): int
    {
        $remaining = $this->cache->decrement($this->key($reportId));

        return is_int($remaining) ? $remaining : 0;
    }

    public function release(string $reportId): void
    {
        $this->cache->forget($this->key($reportId));
    }

    private function key(string $reportId): string
    {
        return 'visual-feedback:refcount:'.$reportId;
    }
}
