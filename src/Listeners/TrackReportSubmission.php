<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Listeners;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Bridges\MatomoBridge;
use Pushery\VisualFeedback\Events\ReportSubmitted;
use Pushery\VisualFeedback\Support\EnvFlag;
use Pushery\VisualFeedback\Support\MatomoEvent;
use Pushery\VisualFeedback\Support\RedactedFailure;
use Throwable;

/**
 * Tracks an accepted report as a Matomo event via the optional bridge. It listens
 * only on ReportSubmitted — never ReportRejected — so a rejected bot submission is never counted
 * as real traffic. Always registered; the bridge's availability guard makes it a no-op without
 * the Matomo package, and `matomo.enabled` switches it off with the package installed.
 */
final readonly class TrackReportSubmission
{
    public function __construct(
        private MatomoBridge $matomo,
        private LoggerInterface $logger,
        private Repository $config,
        private Container $container,
    ) {}

    public function handle(ReportSubmitted $event): void
    {
        $settings = $this->config->get('visual-feedback.matomo');
        $settings = is_array($settings) ? $settings : [];

        if (! EnvFlag::boolean($settings['enabled'] ?? null, true) || ! $this->matomo->isAvailable()) {
            return;
        }

        try {
            if ($this->skips($settings['skip'] ?? null, $event)) {
                return;
            }

            $names = MatomoEvent::names($event->report, $settings);

            $this->matomo->track($names['category'], $names['action'], $event->report->category);
        } catch (Throwable $e) {
            // The belt to the guard's braces, and it is not defensive padding — it encodes which
            // of two failures is allowed to win.
            //
            // This listener runs inside the submit path. Anything it throws propagates to the
            // reporter, so an analytics outage — a misconfigured host, a broken transport, an
            // upstream change in a package this one only optionally knows — would refuse a report
            // that is otherwise perfectly good. A report is written precisely when words were not
            // enough; an event is a number in a dashboard.
            //
            // Logged rather than swallowed, because a bridge that fails silently forever is how a
            // metric quietly goes to zero and nobody asks why. `warning`, not `error`: nothing the
            // application must act on tonight, and the report itself went through.
            $this->logger->warning('visual-feedback: tracking an accepted report failed', [
                'exception' => $e::class,
                'message' => RedactedFailure::message($e),
            ]);
        }
    }

    /**
     * Whether the host's `matomo.skip` hook leaves this report out, an administrator's for example.
     *
     * Asked inside the belt above: a hook that throws, or a key that names nothing invokable, costs
     * the event and a warning in the log, never the report. Only `true` skips, so a hook that
     * answers anything else still counts the report.
     */
    private function skips(mixed $hook, ReportSubmitted $event): bool
    {
        if ($hook === null || $hook === '') {
            return false;
        }

        $skip = is_string($hook) ? $this->container->make($hook) : $hook;

        if (! is_callable($skip)) {
            throw new InvalidArgumentException('visual-feedback.matomo.skip names nothing invokable.');
        }

        return $skip($event) === true;
    }
}
