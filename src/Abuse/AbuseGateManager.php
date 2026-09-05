<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Abuse;

use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Contracts\AbuseGate;
use Throwable;

/**
 * The composite abuse gate. The builtin floor ALWAYS runs first and its
 * rejection is final; any additional driver (botgate) layers ON TOP, never
 * instead. So a challenge-provider outage — or a `driver=botgate` install with no botgate
 * package at all — can never remove the honeypot / time trap / rate limits, which is what
 * makes `abuse.on_error=open` a safe default.
 *
 * Additional drivers FAIL OPEN by design (verified for botgate: Turnstile-verify has a hard
 * timeout and fails open, remote config is fail-safe). An error there leaves the floor's
 * verdict standing and never blocks — a `closed` arm for an additional driver would be a gate
 * arm that can never fire, so it is deliberately not offered.
 */
final readonly class AbuseGateManager implements AbuseGate
{
    /**
     * @param  list<AbuseGate>  $additional  extra gates layered on top of the floor (e.g. botgate)
     */
    public function __construct(
        private BuiltinAbuseGate $floor,
        private array $additional,
        private LoggerInterface $logger,
    ) {}

    public function check(ReportAttempt $attempt): AbuseDecision
    {
        // The floor runs first; its rejection is final and can never be weakened.
        $decision = $this->floor->check($attempt);

        if ($decision->rejected()) {
            return $decision;
        }

        // Additional drivers run on top, failing OPEN: an error leaves the floor's allow
        // standing — the floor already carried the protection.
        foreach ($this->additional as $gate) {
            try {
                $decision = $gate->check($attempt);

                if ($decision->rejected()) {
                    return $decision;
                }
            } catch (Throwable $exception) {
                // `error`, not `warning`. A gate that throws is a DEFECT in that gate, and the
                // consequence is that the challenge it enforces was skipped for this submission —
                // not something to notice at the end of the week. This used to be `warning`, and
                // a warning is where a fail-open goes to be unread.
                $this->logger->error('visual-feedback: additional abuse driver errored (failing open)', [
                    'driver' => $gate::class,
                    'exception' => $exception::class,
                ]);
            }
        }

        return AbuseDecision::allow();
    }
}
