<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Config\Repository as Config;
use Pushery\VisualFeedback\Channels\Webhook\WebhooksPlatform;
use Pushery\VisualFeedback\Contracts\ReportChannel;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Jobs\SendReportWebhook;

/**
 * The webhook delivery channel. It is available when EITHER the
 * pushery/webhooks platform is installed (fleet infrastructure, preferred) OR both a fallback
 * `webhook.url` and a `webhook.secret` are configured — otherwise there is nowhere to deliver
 * safely and it is skipped (see isAvailable() for why the secret is not optional).
 * dispatch() records a pending receipt and enqueues the channel's own SendReportWebhook job,
 * tuned per `channels.webhook` (queue / tries / backoff), so a slow or failing webhook never
 * blocks the submit path and never affects the other channels.
 */
final readonly class WebhookChannel implements ReportChannel
{
    public function __construct(
        private Config $config,
        private Bus $bus,
        private ReceiptStore $receipts,
        private WebhooksPlatform $platform,
    ) {}

    public function key(): string
    {
        return 'webhook';
    }

    /**
     * Available with the fleet platform installed, or a configured fallback URL AND secret.
     *
     * The secret is not optional and the reason is that leaving it out fails OPEN rather than
     * closed. `hash_hmac()` returns the same digest for a null key and an empty one, and the
     * scheme is public — algorithm, "{timestamp}.{rawBody}" input format and header names are
     * constants here and printed on the documentation site. So an unset secret does not produce
     * a broken signature that a receiver rejects; it produces a valid one that anybody who knows
     * the endpoint URL can compute, and the published verification recipe accepts it. A channel
     * that authenticates nothing is worse than one that is switched off, so it reports itself
     * unavailable and the registry logs the skip (it already names an incomplete configuration).
     *
     * A receiver that authenticates by unguessable URL alone — Zapier, n8n, a Slack incoming
     * webhook — ignores the signature header, so any string satisfies this.
     */
    public function isAvailable(): bool
    {
        if ($this->platform->isInstalled()) {
            return true;
        }

        return $this->fallbackUrl() !== null && $this->fallbackSecret() !== null;
    }

    public function dispatch(Report $report): void
    {
        $this->receipts->record($report->id, $this->key(), DeliveryStatus::Pending);

        $job = new SendReportWebhook(
            report: $report,
            includeReporter: $this->includeReporter(),
            tries: $this->tries(),
            backoffSeconds: $this->backoff(),
        );

        $queue = $this->config->get('visual-feedback.channels.webhook.queue');

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        $this->bus->dispatch($job);
    }

    private function includeReporter(): bool
    {
        return (bool) $this->config->get('visual-feedback.webhook.include_reporter', true);
    }

    private function fallbackUrl(): ?string
    {
        $url = $this->config->get('visual-feedback.webhook.url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /** The signing key, or null when it is absent, blank or not a string — never a substitute. */
    private function fallbackSecret(): ?string
    {
        $secret = $this->config->get('visual-feedback.webhook.secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    private function tries(): int
    {
        $tries = $this->config->get('visual-feedback.channels.webhook.tries');

        return is_numeric($tries) && (int) $tries > 0 ? (int) $tries : 3;
    }

    private function backoff(): int
    {
        $backoff = $this->config->get('visual-feedback.channels.webhook.backoff');

        return is_numeric($backoff) && (int) $backoff >= 0 ? (int) $backoff : 30;
    }
}
