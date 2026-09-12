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

    /**
     * An additional abuse driver threw, and it is configured to fail CLOSED.
     *
     * Kept apart from `ChallengeFailed` on purpose, because they mean opposite things about the
     * reporter: that one says the submission was judged and refused, this one says nobody was
     * there to judge it. A host watching rejections sees a burst of both shapes the same way —
     * as an attack — unless the reason tells them one of the bursts is their own provider being
     * down, in which case the fix is an incident page and not a firewall rule.
     */
    case GateUnavailable = 'gate_unavailable';

    /**
     * The instance-wide cap was full — `abuse.global_rate_limit`, counted across every reporter.
     *
     * Separate from `RateLimited` because the two name different subjects and call for different
     * reactions. That one says this sender has had their share; this one says the whole
     * application has, which is either a distributed bot or a day nobody planned for. A host that
     * cannot tell them apart reads a burst of `RateLimited` as ordinary traffic shaping and never
     * learns that legitimate reporters are being turned away.
     */
    case GlobalRateLimited = 'global_rate_limited';

    /**
     * The install is sign-in only (`require_authentication`) and this submission came from a
     * guest session.
     *
     * Not an abuse verdict and not a validation error, though it would be easy to file it as
     * either. It is the same class of thing as `Disabled`: an operator decided who may report,
     * and somebody arrived outside that decision — most often a real person whose session expired
     * while the tab was open, occasionally a request addressed at the Livewire component without
     * the page that draws it. A host who cannot tell this from a missing required field cannot
     * tell a session-timeout problem from a form problem.
     */
    case AuthenticationRequired = 'authentication_required';
}
