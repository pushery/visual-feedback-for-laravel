<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Listeners;

use Pushery\VisualFeedback\Bridges\MatomoBridge;
use Pushery\VisualFeedback\Events\ReportSubmitted;

/**
 * Tracks an accepted report as a Matomo event via the optional bridge. It listens
 * only on ReportSubmitted — never ReportRejected — so a rejected bot submission is never counted
 * as real traffic. Always registered; the bridge's availability guard makes it a no-op without
 * the Matomo package.
 */
final readonly class TrackReportSubmission
{
    public function __construct(private MatomoBridge $matomo) {}

    public function handle(ReportSubmitted $event): void
    {
        if ($this->matomo->isAvailable()) {
            $this->matomo->recordSubmission($event->report->category);
        }
    }
}
