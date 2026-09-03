<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Submission;

use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Events\RejectionReason;

/**
 * The outcome of a submission. `showsSuccess` drives the UI independently of `accepted`
 * so a honeypot hit renders a DECOY success (nothing was stored, but the bot sees the
 * same success as a human). A validation or listener rejection shows an error instead.
 */
final readonly class SubmissionResult
{
    private function __construct(
        public bool $accepted,
        public bool $showsSuccess,
        public ?Report $report,
        public ?RejectionReason $rejectionReason,
        public ?ValidationFailure $failure = null,
    ) {}

    public static function accepted(Report $report): self
    {
        return new self(accepted: true, showsSuccess: true, report: $report, rejectionReason: null);
    }

    /** A silently-rejected submission (e.g. honeypot): nothing stored, but the UI shows success. */
    public static function silentlyRejected(RejectionReason $reason): self
    {
        return new self(accepted: false, showsSuccess: true, report: null, rejectionReason: $reason);
    }

    /**
     * A rejected submission the reporter should see as an error (validation, listener veto).
     *
     * `$failure` names the field and carries the message where there is one, so the widget can
     * point at the control that failed instead of at the message box. Without it the widget can
     * only say "something went wrong", which is what it used to say for every rejection.
     */
    public static function rejected(RejectionReason $reason, ?ValidationFailure $failure = null): self
    {
        return new self(
            accepted: false,
            showsSuccess: false,
            report: null,
            rejectionReason: $reason,
            failure: $failure,
        );
    }
}
