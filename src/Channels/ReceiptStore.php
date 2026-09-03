<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Per-report delivery receipts, in the CACHE — so "which channel delivered this report?" is
 * answerable even for a mail-only consumer with no database table. This fixes the single-column
 * approach, where one `email_sent_at` stays NULL forever: each report UUID holds a receipt map
 * `{channel: pending|delivered|failed}`, existing independently of which channels are active. A
 * single column could never express mail=delivered while webhook=failed; a map can.
 *
 * The optional database row (DatabaseChannel) is a SEPARATE, richer copy; this
 * cache store is the always-present source of truth. TTL tracks the report retention window.
 */
final readonly class ReceiptStore
{
    public function __construct(private Cache $cache, private Config $config) {}

    /** Record a channel's delivery status for a report (idempotent: re-recording is a no-op change). */
    public function record(string $reportId, string $channel, DeliveryStatus $status): void
    {
        $map = $this->rawMap($reportId);
        $map[$channel] = $status->value;

        $this->cache->put($this->key($reportId), $map, $this->ttlSeconds());
    }

    /**
     * The report's receipt map, channel → status. Empty when nothing has been recorded yet.
     *
     * @return array<string, DeliveryStatus>
     */
    public function receipts(string $reportId): array
    {
        $receipts = [];

        foreach ($this->rawMap($reportId) as $channel => $value) {
            $status = is_string($value) ? DeliveryStatus::tryFrom($value) : null;

            if (is_string($channel) && $status instanceof DeliveryStatus) {
                $receipts[$channel] = $status;
            }
        }

        return $receipts;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function rawMap(string $reportId): array
    {
        $map = $this->cache->get($this->key($reportId));

        return is_array($map) ? $map : [];
    }

    private function key(string $reportId): string
    {
        return 'visual-feedback:receipts:'.$reportId;
    }

    /** Receipts live as long as the reports they describe — the report retention window. */
    private function ttlSeconds(): int
    {
        $days = $this->config->get('visual-feedback.retention.reports_days');

        return max(1, is_numeric($days) ? (int) $days : 90) * 86_400;
    }
}
