<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Privacy;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Pushery\VisualFeedback\Data\Report;

/**
 * The reporters `visual-feedback:forget` erased, kept so that a delivery still queued for one of
 * them does not bring a copy back.
 *
 * A delivery job carries the whole report. One that was waiting, backing off or retried later
 * from the failed jobs would otherwise store, mail or post the report after the erasure was
 * reported done. The jobs ask here first. A marker lives as long as a stored report would,
 * `retention.reports_days`, in the default cache store, which the delivery receipts already
 * require the web server and its workers to share.
 *
 * Three kinds of marker, one per kind of erasure: every report under an address, only the guest
 * reports under an address, and every report of a signed-in reporter by id, under whatever
 * address it used. An address is compared without case, and every key is stored only as a hash.
 */
final readonly class ErasedReporters
{
    public function __construct(private Cache $cache, private Config $config) {}

    /** Every report under this address, signed in or not. */
    public function remember(string $email): void
    {
        $this->mark($this->key('', mb_strtolower(trim($email))));
    }

    /** Only the reports sent as a guest under this address. */
    public function rememberGuest(string $email): void
    {
        $this->mark($this->key('guest:', mb_strtolower(trim($email))));
    }

    /** Every report of the signed-in reporter with this id, under any address. */
    public function rememberReporter(string $id): void
    {
        $this->mark($this->key('reporter:', $id));
    }

    /** Whether the reporter of this report was erased after the report was submitted. */
    public function covers(Report $report): bool
    {
        $reporter = $report->reporter;
        $submittedAt = $report->submittedAt->getTimestamp();
        $email = is_string($reporter->email) && trim($reporter->email) !== '' ? mb_strtolower(trim($reporter->email)) : null;

        if ($email !== null && $this->erasedSince($this->key('', $email), $submittedAt)) {
            return true;
        }

        if ($email !== null && $reporter->isGuest && $this->erasedSince($this->key('guest:', $email), $submittedAt)) {
            return true;
        }

        return is_string($reporter->id) && $reporter->id !== '' && $this->erasedSince($this->key('reporter:', $reporter->id), $submittedAt);
    }

    private function mark(string $key): void
    {
        $this->cache->put($key, Carbon::now()->getTimestamp(), $this->ttlSeconds());
    }

    private function erasedSince(string $key, int $submittedAt): bool
    {
        $erasedAt = $this->cache->get($key);

        return is_int($erasedAt) && $erasedAt >= $submittedAt;
    }

    /** The cache key of a marker: its kind and a hash of what it names. */
    private function key(string $kind, string $subject): string
    {
        return 'visual-feedback:erased:'.$kind.hash('sha256', $subject);
    }

    private function ttlSeconds(): int
    {
        $days = $this->config->get('visual-feedback.retention.reports_days');

        return max(1, is_numeric($days) ? (int) $days : 90) * 86_400;
    }
}
