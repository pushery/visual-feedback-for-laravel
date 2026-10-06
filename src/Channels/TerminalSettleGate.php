<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The once-only latch behind "every channel settles exactly once". That
 * sentence is ReportDeliveryTracker's whole contract, and it used to rest on every caller being
 * disciplined enough to keep it — which is not a property code has, it is a property nobody is
 * checking.
 *
 * The sync queue broke it with two individually correct pieces. `SyncQueue::handleException()`
 * calls `$job->fail($e)`, which runs the job's failed() hook (one settle), and then rethrows.
 * That exception leaves Bus::dispatch(), leaves the channel's dispatch(), and lands in
 * ChannelRegistry's per-channel try/catch, which settles the same channel a second time. The
 * receipt survived that (writing the same status twice is a no-op) but the two things beside it
 * did not: ReportDeliveryFailed fired twice for one failure — a documented event consumers count
 * and alert on — and the attachment refcount was decremented twice, so the files were released
 * one settle early, before a later transient channel had even been dispatched.
 *
 * So the terminal settle is claimed here instead of counted by convention: the first caller for
 * a (report, channel) pair gets true, every later one gets false, and the tracker returns without
 * doing anything. That makes the second call structurally inert no matter which caller makes it,
 * rather than repairing the one path that is known to duplicate today.
 *
 * It is a cache `add()` — one key, one atomic write, no read-modify-write — and deliberately not a
 * ReceiptStore read: a receipt says which status a channel has, and writing the same status a
 * second time looks exactly like writing it once, so no receipt can tell the first settle from
 * the second. The TTL matches the refcount's: comfortably past the longest queue retry horizon.
 */
final readonly class TerminalSettleGate
{
    /** A generous week — the same horizon the attachment refcount uses, for the same reason. */
    private const int TTL_SECONDS = 7 * 24 * 60 * 60;

    public function __construct(private Cache $cache) {}

    /** True for the first terminal settle of this (report, channel), false for every later one. */
    public function claim(string $reportId, string $channel): bool
    {
        return $this->cache->add($this->key($reportId, $channel), true, self::TTL_SECONDS);
    }

    /**
     * Hand a claim back, so the next terminal settle of the pair counts as the first again.
     *
     * Only for a settle that failed before it wrote anything: the tracker hands the claim back
     * when the receipt itself could not be written, never after the event or the refcount step,
     * which would then run twice.
     */
    public function release(string $reportId, string $channel): void
    {
        $this->cache->forget($this->key($reportId, $channel));
    }

    private function key(string $reportId, string $channel): string
    {
        return 'visual-feedback:settled:'.$reportId.':'.$channel;
    }
}
