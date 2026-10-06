<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use LogicException;
use Pushery\VisualFeedback\Support\RedactedFailure;
use Throwable;

/**
 * Per-report delivery receipts, in the cache — so "which channel delivered this report?" is
 * answerable even for a mail-only consumer with no database table. This fixes the single-column
 * approach, where one `email_sent_at` stays NULL forever: each report UUID holds a receipt map
 * `{channel: pending|delivered|failed}`, existing independently of which channels are active. A
 * single column could never express mail=delivered while webhook=failed; a map can.
 *
 * The optional database row (DatabaseChannel) is a separate, richer copy; this
 * cache store is the always-present source of truth. TTL tracks the report retention window.
 *
 * Writers do not share a cache value. The request records `pending` for a channel while a worker
 * may already settle an earlier one, and two workers can settle two channels at the same instant,
 * so each channel's status is a key of its own, written with one put. Which channels a report has
 * is a list of slots, and a channel takes a free slot with add(), which writes only an empty one:
 * listing a channel never rewrites a slot another writer filled. A single map read and put back
 * whole lost whatever a concurrent writer had put in between. Receipts recorded in that map by an
 * earlier release are still read, under any status recorded since.
 */
final readonly class ReceiptStore
{
    /** The channels one report can carry receipts for, far above what a registry holds. */
    private const int SLOTS = 32;

    public function __construct(private Cache $cache, private Config $config) {}

    /** Record a channel's delivery status for a report (idempotent: re-recording is a no-op change). */
    public function record(string $reportId, string $channel, DeliveryStatus $status): void
    {
        $this->cache->put($this->statusKey($reportId, $channel), $status->value, $this->ttlSeconds());
        $this->list($reportId, $channel);
    }

    /**
     * Record that a channel has taken the report, as far as the cache can say so.
     *
     * The pending receipt tells a reader that a delivery is under way, and the channel's terminal
     * settle writes the receipt that counts. A channel records it before it queues its job, so a
     * store that throws here would stop the job from being queued, and a cache outage would cost
     * the report itself. A failed write is reported and passed over.
     */
    public function recordPending(string $reportId, string $channel): void
    {
        try {
            $this->record($reportId, $channel, DeliveryStatus::Pending);
        } catch (Throwable $exception) {
            report(RedactedFailure::standIn($exception));
        }
    }

    /**
     * The report's receipt map, channel → status. Empty when nothing has been recorded yet.
     *
     * @return array<string, DeliveryStatus>
     */
    public function receipts(string $reportId): array
    {
        $earlier = $this->earlierMap($reportId);
        $channels = array_values(array_unique([
            ...array_values(array_filter(array_keys($earlier), is_string(...))),
            ...array_values(array_filter($this->slots($reportId), is_string(...))),
        ]));

        if ($channels === []) {
            return [];
        }

        $values = $this->cache->many(array_map(fn (string $channel): string => $this->statusKey($reportId, $channel), $channels));
        $receipts = [];

        foreach ($channels as $channel) {
            $value = $values[$this->statusKey($reportId, $channel)] ?? $earlier[$channel] ?? null;
            $status = is_string($value) ? DeliveryStatus::tryFrom($value) : null;

            if ($status instanceof DeliveryStatus) {
                $receipts[$channel] = $status;
            }
        }

        return $receipts;
    }

    /** Take a free slot for the channel, unless a slot already names it. */
    private function list(string $reportId, string $channel): void
    {
        $slots = $this->slots($reportId);

        if (in_array($channel, $slots, true)) {
            return;
        }

        foreach ($slots as $slot => $listed) {
            if ($listed !== null) {
                continue;
            }

            // A writer that lost the slot to another one reads whose it is: the same channel,
            // listed by a concurrent record of it, needs no second slot.
            if ($this->cache->add($slot, $channel, $this->ttlSeconds()) || $this->cache->get($slot) === $channel) {
                return;
            }
        }

        throw new LogicException('A report carries receipts for at most '.self::SLOTS.' channels.');
    }

    /**
     * Every slot of the report, keyed by its cache key, with the channel it lists or null.
     *
     * @return array<string, ?string>
     */
    private function slots(string $reportId): array
    {
        $keys = [];

        for ($slot = 0; $slot < self::SLOTS; $slot++) {
            $keys[] = 'visual-feedback:receipt-channel:'.$reportId.':'.$slot;
        }

        $values = $this->cache->many($keys);
        $slots = [];

        foreach ($keys as $key) {
            $channel = $values[$key] ?? null;
            $slots[$key] = is_string($channel) ? $channel : null;
        }

        return $slots;
    }

    /**
     * The single map an earlier release kept, for a report it recorded.
     *
     * @return array<array-key, mixed>
     */
    private function earlierMap(string $reportId): array
    {
        $map = $this->cache->get('visual-feedback:receipts:'.$reportId);

        return is_array($map) ? $map : [];
    }

    private function statusKey(string $reportId, string $channel): string
    {
        return 'visual-feedback:receipt:'.$reportId.':'.$channel;
    }

    /** Receipts live as long as the reports they describe — the report retention window. */
    private function ttlSeconds(): int
    {
        $days = $this->config->get('visual-feedback.retention.reports_days');

        return max(1, is_numeric($days) ? (int) $days : 90) * 86_400;
    }
}
