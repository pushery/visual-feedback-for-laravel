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
 * address it used. An address is compared without case, and every key holds what it names only as
 * a hash keyed with the application key: a cache key is what an error tracker records, and a
 * plain hash of an address is undone by hashing every candidate, which would tell whoever reads
 * the keys that this person asked to be erased. A marker set before the application key was
 * rotated still holds while `app.previous_keys` lists the old key.
 */
final readonly class ErasedReporters
{
    public function __construct(private Cache $cache, private Config $config) {}

    /** Every report under this address, signed in or not. */
    public function remember(string $email): void
    {
        $this->mark('', mb_strtolower(trim($email)));
    }

    /** Only the reports sent as a guest under this address. */
    public function rememberGuest(string $email): void
    {
        $this->mark('guest:', mb_strtolower(trim($email)));
    }

    /** Every report of the signed-in reporter with this id, under any address. */
    public function rememberReporter(string $id): void
    {
        $this->mark('reporter:', $id);
    }

    /** Whether the reporter of this report was erased after the report was submitted. */
    public function covers(Report $report): bool
    {
        $reporter = $report->reporter;
        $submittedAt = $report->submittedAt->getTimestamp();
        $email = is_string($reporter->email) && trim($reporter->email) !== '' ? mb_strtolower(trim($reporter->email)) : null;

        if ($email !== null && $this->erasedSince('', $email, $submittedAt)) {
            return true;
        }

        if ($email !== null && $reporter->isGuest && $this->erasedSince('guest:', $email, $submittedAt)) {
            return true;
        }

        return is_string($reporter->id) && $reporter->id !== '' && $this->erasedSince('reporter:', $reporter->id, $submittedAt);
    }

    private function mark(string $kind, string $subject): void
    {
        $this->cache->put($this->key($kind, $subject, $this->applicationKey()), Carbon::now()->getTimestamp(), $this->ttlSeconds());
    }

    /** Whether a marker of this kind names the subject, set at or after the submission, under the key or a previous one. */
    private function erasedSince(string $kind, string $subject, int $submittedAt): bool
    {
        foreach ([$this->applicationKey(), ...$this->previousKeys()] as $applicationKey) {
            $erasedAt = $this->cache->get($this->key($kind, $subject, $applicationKey));

            // Redis stores a number without serializing it and hands it back as a numeric string, while an
            // in-memory store returns the int that went in, so the marker is read as a number.
            if (is_numeric($erasedAt) && (int) $erasedAt >= $submittedAt) {
                return true;
            }
        }

        return false;
    }

    /** The cache key of a marker: its kind and a hash of what it names, keyed with an application key. */
    private function key(string $kind, string $subject, string $applicationKey): string
    {
        return 'visual-feedback:erased:'.$kind.hash_hmac('sha256', $subject, $applicationKey);
    }

    /** The application key, which keys every marker. Empty where none is set. */
    private function applicationKey(): string
    {
        $key = $this->config->get('app.key');

        return is_string($key) ? $key : '';
    }

    /**
     * The keys the application rotated away from and still accepts, as `config/app.php` lists them.
     *
     * @return list<string>
     */
    private function previousKeys(): array
    {
        $listed = $this->config->get('app.previous_keys');
        $keys = [];

        foreach (is_array($listed) ? $listed : [] as $key) {
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function ttlSeconds(): int
    {
        $days = $this->config->get('visual-feedback.retention.reports_days');

        return max(1, is_numeric($days) ? (int) $days : 90) * 86_400;
    }
}
