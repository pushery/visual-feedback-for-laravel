<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The once-only latch behind "every channel settles EXACTLY ONCE". That
 * sentence is ReportDeliveryTracker's whole contract, and it used to rest on every caller being
 * disciplined enough to keep it — which is not a property code has, it is a property nobody is
 * checking.
 *
 * The sync queue broke it with two individually correct pieces. `SyncQueue::handleException()`
 * calls `$job->fail($e)`, which runs the job's failed() hook (one settle), and then RETHROWS.
 * That exception leaves Bus::dispatch(), leaves the channel's dispatch(), and lands in
 * ChannelRegistry's per-channel try/catch, which settles the same channel a second time. The
 * receipt survived that (writing the same status twice is a no-op) but the two things beside it
 * did not: ReportDeliveryFailed fired twice for one failure — a documented event consumers count
 * and alert on — and the attachment refcount was decremented twice, so the files were released
 * one settle early, before a later transient channel had even been dispatched.
 *
 * So the terminal settle is CLAIMED here instead of counted by convention: the first caller for
 * a (report, channel) pair gets true, every later one gets false, and the tracker returns without
 * doing anything. That makes the second call structurally inert no matter which caller makes it,
 * rather than repairing the one path that is known to duplicate today.
 *
 * It is a cache `add()` — one key, one atomic write, no read-modify-write — deliberately not the
 * ReceiptStore's status map, which is a get-then-put over a single key holding EVERY channel of a
 * report and therefore loses an update when two workers settle at the same instant. Making
 * cleanup correctness depend on that map would trade a sync-queue defect for a real-worker one.
 * The TTL matches the refcount's: comfortably past the longest queue retry horizon.
 */
final readonly class TerminalSettleGate
{
    /** A generous week — the same horizon the attachment refcount uses, for the same reason. */
    private const int TTL_SECONDS = 7 * 24 * 60 * 60;

    public function __construct(private Cache $cache) {}

    /** True for the FIRST terminal settle of this (report, channel), false for every later one. */
    public function claim(string $reportId, string $channel): bool
    {
        return $this->cache->add($this->key($reportId, $channel), true, self::TTL_SECONDS);
    }

    private function key(string $reportId, string $channel): string
    {
        return 'visual-feedback:settled:'.$reportId.':'.$channel;
    }
}
