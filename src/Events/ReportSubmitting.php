<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Events;

use Pushery\VisualFeedback\Data\Report;

/**
 * Dispatched before a report is accepted, giving listeners a last chance to cancel it.
 *
 * This is the ONE mutable event in the family: a listener calls `reject()` to cancel.
 * Only SYNCHRONOUS listeners can cancel — a queued listener runs after the report has
 * already been accepted and dispatched to channels, far too late. A rejection here
 * surfaces to the reporter as an error state (no channel dispatch) and fires
 * `ReportRejected(RejectionReason::ListenerRejected)`.
 */
final class ReportSubmitting
{
    private ?string $rejectionReason = null;

    public function __construct(public readonly Report $report) {}

    /**
     * Cancel this submission (synchronous listeners only). The optional reason is
     * carried into the resulting ReportRejected event for observability.
     */
    public function reject(string $reason = ''): void
    {
        $this->rejectionReason = $reason;
    }

    public function isRejected(): bool
    {
        return $this->rejectionReason !== null;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }
}
