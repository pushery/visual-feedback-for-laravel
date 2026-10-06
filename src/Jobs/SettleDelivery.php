<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pushery\VisualFeedback\Channels\ReportDeliveryTracker;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Support\RedactedFailure;
use Throwable;

/**
 * The settle of a delivery that has already gone out, as a job of its own.
 *
 * A delivering job sends first and settles after, and the settle writes to the cache: the claim,
 * the receipt, the event and the attachment counter. When the cache refuses that write, failing
 * the delivering job would make the queue retry it, and the retry would send the mail or post the
 * webhook again. So the delivering job ends, and this job retries the settle alone, on the same
 * connection and queue, with the channel's tries and backoff, until the cache takes it.
 *
 * Queued encrypted, like the delivering jobs: it carries the report, whose files the last settle
 * of a report deletes.
 */
final class SettleDelivery implements ShouldBeEncrypted, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Report $report,
        public readonly string $channel,
        public readonly int $tries,
        private readonly int $backoffSeconds,
    ) {}

    /**
     * Settle a delivery that went out, or hand the settle to a job of its own when it fails.
     *
     * Nothing a delivering job does after this line may fail it. When even the hand-off cannot be
     * queued, both failures are reported and the delivery stays unsettled, which leaves its
     * files to the orphan sweep; that is the better outcome than a second copy of the report.
     */
    public static function afterDelivery(ReportDeliveryTracker $tracker, Report $report, string $channel, ?string $connection, ?string $queue, int $tries, int $backoffSeconds): void
    {
        try {
            $tracker->settleDelivered($report, $channel);
        } catch (Throwable $exception) {
            report(RedactedFailure::standIn($exception));

            try {
                Container::getInstance()->make(Dispatcher::class)->dispatch(
                    new self($report, $channel, $tries, $backoffSeconds)->onConnection($connection)->onQueue($queue),
                );
            } catch (Throwable $handOff) {
                report(RedactedFailure::standIn($handOff));
            }
        }
    }

    public function handle(ReportDeliveryTracker $tracker): void
    {
        // What a job throws, the worker reports and the failed jobs keep, so a cache error that
        // names a DSN goes out as a stand-in without its secrets.
        try {
            $tracker->settleDelivered($this->report, $this->channel);
        } catch (Throwable $exception) {
            throw RedactedFailure::standIn($exception);
        }
    }

    public function backoff(): int
    {
        return $this->backoffSeconds;
    }
}
