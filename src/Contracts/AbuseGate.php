<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Contracts;

use Pushery\VisualFeedback\Abuse\AbuseDecision;
use Pushery\VisualFeedback\Abuse\ReportAttempt;

/**
 * The abuse-protection seam. A driver is resolved from `abuse.driver`, but the
 * builtin floor (honeypot + server-anchored time trap + rate limits) ALWAYS runs underneath it —
 * so a challenge-provider outage or a misconfiguration can never remove all protection, and
 * `abuse.on_error=open` is a safe default. check() is called on EVERY submit attempt, before
 * validation, so a failing attempt still costs the attacker a rate-limit token.
 */
interface AbuseGate
{
    public function check(ReportAttempt $attempt): AbuseDecision;
}
