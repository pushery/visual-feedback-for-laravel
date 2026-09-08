<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Submission;

use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Events\RejectionReason;

/**
 * The outcome of a submission. `showsSuccess` drives the UI independently of `accepted`
 * so a honeypot hit renders a DECOY success (nothing was stored, but the bot sees the
 * same success as a human). A validation or listener rejection shows an error instead.
 *
 * The two flags therefore come apart in BOTH directions, and the second one is new: a honeypot
 * hit is `accepted: false, showsSuccess: true`, and a report nothing was there to carry is
 * `accepted: true, showsSuccess: false` — real, stored where a store is configured, and not
 * something to thank the reporter for.
 */
final readonly class SubmissionResult
{
    private function __construct(
        public bool $accepted,
        public bool $showsSuccess,
        public ?Report $report,
        public ?RejectionReason $rejectionReason,
        public ?ValidationFailure $failure = null,
        public bool $handedToAChannel = false,
    ) {}

    /**
     * `$handedToAChannel` is what separates "somebody took this report" from "it was accepted and
     * dropped", and until it existed those two produced the identical success state.
     *
     * The dropped case is not exotic: this package ships `channels.mail` on and
     * `channels.database` off, so a host whose only channel reports itself unavailable — no
     * recipient, or a transport that accepts and discards — delivers nowhere. The registry logs
     * it, but a log line is not something the reporter can read.
     *
     * False does NOT mean the report failed to arrive at its destination; that answer comes later,
     * from the receipt a queued job settles. It means nothing was even asked to carry it.
     */
    public static function accepted(Report $report, bool $handedToAChannel = true): self
    {
        return new self(
            accepted: true,
            showsSuccess: $handedToAChannel,
            report: $report,
            rejectionReason: null,
            handedToAChannel: $handedToAChannel,
        );
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
