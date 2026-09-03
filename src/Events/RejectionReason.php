<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Events;

/**
 * Why a submission was rejected. Every rejection — including a silently-successful
 * honeypot hit — carries one of these, so a host can observe rejections even when the
 * UI shows a decoy success.
 */
enum RejectionReason: string
{
    case Honeypot = 'honeypot';
    case RateLimited = 'rate_limited';
    case Validation = 'validation';
    case ChallengeFailed = 'challenge_failed';
    case ListenerRejected = 'listener_rejected';

    /**
     * The package's master switch is off.
     *
     * Not an abuse verdict — an operator decision — but it arrives on the same path and a host
     * observing rejections wants to see it, because "the form stopped accepting" and "the form
     * is under attack" look identical from the outside otherwise.
     */
    case Disabled = 'disabled';
}
