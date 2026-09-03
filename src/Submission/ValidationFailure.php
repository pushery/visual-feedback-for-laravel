<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Submission;

/**
 * The first validation failure of a submission: WHICH field, and what to tell the reporter.
 *
 * The field traveled nowhere before this existed. Every rejection reached the widget as a bare
 * `RejectionReason::Validation`, so the widget said "Something went wrong. Please try again." and
 * moved focus to the message field — whatever had actually failed. A reporter whose email had a
 * typo was told nothing and pointed at the wrong control, which is exactly the
 * situation WCAG 3.3.1 and 3.3.3 are about: an error has to be identified and described.
 *
 * The field name is the DOMAIN key (`guest_email`), not a DOM id. Mapping it to a control is the
 * view's business, and the two view trees do it differently.
 */
final readonly class ValidationFailure
{
    public function __construct(
        public string $field,
        public string $message,
    ) {}
}
