<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Events;

/**
 * Dispatched the moment the instance-wide report cap is reached — ONCE per window, not once per
 * refused submission.
 *
 * The per-subject rate limits answer one sender at a time and a distributed bot never meets them:
 * a thousand addresses each stay under the guest limit and together produce a thousand reports an
 * hour, every one of which is a mail with its attachments on a provider that bills per message and
 * per byte. `abuse.global_rate_limit` is the ceiling that bounds the bill, and this event is how an
 * operator learns they are at it from their own application rather than from the invoice.
 *
 * It fires on the attempt that REACHES the cap, which is the last one still accepted — so it
 * arrives one report before anything is refused, and an operator who reacts fast enough loses
 * nothing. Every refusal after it is observable as usual through
 * `ReportRejected(RejectionReason::GlobalRateLimited)`; this event stays quiet for those, because
 * the whole point of a dedicated signal is that it can be wired to something that wakes a person
 * up, and a signal that repeats a thousand times an hour cannot be.
 *
 * Carries no report and no reporter: the cap is a property of the instance, and by definition the
 * submissions that filled it came from many different senders.
 */
final readonly class InstanceRateLimitReached
{
    public function __construct(
        /** The configured ceiling that was reached, in reports per window. */
        public int $limit,
        /** The window the count decays over, in seconds. */
        public int $windowSeconds,
    ) {}
}
