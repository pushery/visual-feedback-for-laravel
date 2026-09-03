<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Events;

/**
 * Dispatched when a submission is rejected, for any reason. It fires even for a
 * honeypot hit that shows the reporter a decoy success, so rejections stay observable.
 * It carries only an enum and a string, so it is always queue-listener-safe — there is
 * deliberately no full Report, because a rejection can happen before one is built.
 */
final readonly class ReportRejected
{
    public function __construct(
        public RejectionReason $reason,
        public ?string $detail = null,
    ) {}
}
