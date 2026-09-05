<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Abuse;

use Pushery\VisualFeedback\Events\RejectionReason;

/**
 * The verdict of an AbuseGate check. It is either "allow" or a rejection that
 * carries the exact RejectionReason, so the submit pipeline can fire the matching
 * ReportRejected event without re-deriving why. Built only through the two named
 * factories, so an allowed decision can never accidentally carry a reason (or a rejection lack one).
 *
 * A rejection also carries whether the reporter is TOLD. Silence is the default and the right
 * answer for anything a bot triggers — a honeypot hit or a forged token gets the same decoy
 * success screen a real submission gets, so an attacker learns nothing. It is the wrong answer for
 * something a HUMAN can fail: a rate limit they will be under again in an hour, or an interactive
 * challenge they simply got wrong. Those people need to be told, or they walk away believing they
 * were heard.
 *
 * The gate decides, because only the gate knows which of the two it just caught. The pipeline used
 * to decide instead, by comparing the reason against one hardcoded case — which meant every reason
 * a CONSUMER's gate could return, `ChallengeFailed` included, silently rendered success.
 */
final readonly class AbuseDecision
{
    private function __construct(
        public bool $allowed,
        public ?RejectionReason $reason,
        public bool $visible,
        public ?string $detail = null,
    ) {}

    public static function allow(): self
    {
        return new self(true, null, false);
    }

    /**
     * @param  bool  $visible  whether the reporter is shown the rejection. Defaults to FALSE — the
     *                         safe answer when a gate does not say, because a silent rejection can
     *                         only ever cost a real person their message, while a visible one hands
     *                         an attacker a working detector.
     * @param  ?string  $detail  a machine-readable note for the HOST's listener, never for the
     *                           reporter. `RejectionReason` is a small enum on purpose and adding
     *                           a case to it is an API change; this string is free, and it is what
     *                           lets a host tell three different situations apart when the enum
     *                           gives them one name. Optional: a gate that says nothing loses
     *                           nothing.
     */
    public static function reject(RejectionReason $reason, bool $visible = false, ?string $detail = null): self
    {
        return new self(false, $reason, $visible, $detail);
    }

    public function rejected(): bool
    {
        return ! $this->allowed;
    }
}
