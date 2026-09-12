<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Abuse;

use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Contracts\AbuseGate;
use Pushery\VisualFeedback\Events\RejectionReason;
use Pushery\VisualFeedback\Support\Settings;
use Throwable;

/**
 * The composite abuse gate. The builtin floor ALWAYS runs first and its
 * rejection is final; any additional driver (botgate) layers ON TOP, never
 * instead. So a challenge-provider outage — or a `driver=botgate` install with no botgate
 * package at all — can never remove the honeypot / time trap / rate limits, which is what
 * makes `abuse.on_error=open` a safe default.
 *
 * Additional drivers fail OPEN by default, and that default is unchanged: an error there leaves
 * the floor's verdict standing rather than taking a consumer's feedback form offline because a
 * third party is having an outage.
 *
 * THIS USED TO SAY A `closed` ARM "WOULD BE A GATE ARM THAT CAN NEVER FIRE", AND THAT WAS WRONG
 * ABOUT WHO PAYS. It reasoned from the floor still being there, which is true and beside
 * the point: the floor is a honeypot, a time trap and a per-IP hourly limit, and for an install
 * that sends mail with attachments per delivered report, "only the floor" is a bill rather than a
 * protection. That trade belongs to the host, so `abuse.drivers.<name>.on_error = 'closed'` now
 * offers it — per driver, because two layered gates can differ in how much their absence costs.
 */
final readonly class AbuseGateManager implements AbuseGate
{
    /**
     * @param  array<string, AbuseGate>  $additional  extra gates layered on top of the floor,
     *                                                keyed by the `abuse.driver` name that
     *                                                selected them — the same name their
     *                                                `on_error` mode is configured under
     */
    public function __construct(
        private BuiltinAbuseGate $floor,
        private array $additional,
        private LoggerInterface $logger,
        private Settings $settings,
    ) {}

    public function check(ReportAttempt $attempt): AbuseDecision
    {
        // The floor runs first; its rejection is final and can never be weakened.
        $decision = $this->floor->check($attempt);

        if ($decision->rejected()) {
            return $decision;
        }

        // Additional drivers run on top. An error in one of them resolves per driver: OPEN (the
        // shipped default) leaves the floor's allow standing, CLOSED refuses while the driver is
        // not answering.
        foreach ($this->additional as $name => $gate) {
            try {
                $decision = $gate->check($attempt);

                if ($decision->rejected()) {
                    return $decision;
                }
            } catch (Throwable $exception) {
                $opens = $this->settings->additionalGateOpensOnError($name);

                // `error`, not `warning`, and it is logged under BOTH modes. A gate that throws is
                // a DEFECT in that gate, and the consequence is that the challenge it enforces was
                // skipped for this submission — not something to notice at the end of the week.
                // This used to be `warning`, and a warning is where a fail-open goes to be unread.
                //
                // Under `closed` the line matters just as much for the opposite reason: from the
                // reporter's side a refusal looks like ordinary bot defense, and this is the only
                // record that says the form was turning people away because a provider was down.
                $this->logger->error(
                    'visual-feedback: additional abuse driver errored (failing '.($opens ? 'open' : 'closed').')',
                    [
                        // Which code failed, and — separately — which configured name governs it.
                        // They are not the same thing and an operator needs both: the class says
                        // where to look, the name says which `abuse.drivers.<name>.on_error` key
                        // decided what just happened.
                        'driver' => $gate::class,
                        'configured_driver' => $name,
                        'on_error' => $opens ? 'open' : 'closed',
                        'exception' => $exception::class,
                    ],
                );

                if (! $opens) {
                    // Its own reason rather than ChallengeFailed: a host watching rejections has
                    // to be able to tell "this submission failed the challenge" from "there was no
                    // challenge to fail", and those two arrive on the same event.
                    return AbuseDecision::reject(RejectionReason::GateUnavailable);
                }
            }
        }

        return AbuseDecision::allow();
    }
}
