<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Listeners;

use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Bridges\MatomoBridge;
use Pushery\VisualFeedback\Events\ReportSubmitted;
use Throwable;

/**
 * Tracks an accepted report as a Matomo event via the optional bridge. It listens
 * only on ReportSubmitted — never ReportRejected — so a rejected bot submission is never counted
 * as real traffic. Always registered; the bridge's availability guard makes it a no-op without
 * the Matomo package.
 */
final readonly class TrackReportSubmission
{
    public function __construct(private MatomoBridge $matomo, private LoggerInterface $logger) {}

    public function handle(ReportSubmitted $event): void
    {
        if (! $this->matomo->isAvailable()) {
            return;
        }

        try {
            $this->matomo->recordSubmission($event->report->category);
        } catch (Throwable $e) {
            // The belt to the guard's braces, and it is not defensive padding — it encodes which
            // of two failures is allowed to win.
            //
            // This listener runs inside the submit path. Anything it throws propagates to the
            // reporter, so an analytics outage — a misconfigured host, a broken transport, an
            // upstream change in a package this one only optionally knows — would refuse a report
            // that is otherwise perfectly good. A report is written precisely when words were not
            // enough; an event is a number in a dashboard.
            //
            // Logged rather than swallowed, because a bridge that fails silently forever is how a
            // metric quietly goes to zero and nobody asks why. `warning`, not `error`: nothing the
            // application must act on tonight, and the report itself went through.
            $this->logger->warning('visual-feedback: tracking an accepted report failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
