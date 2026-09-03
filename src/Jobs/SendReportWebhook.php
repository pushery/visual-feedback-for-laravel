<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Channels\ReportDeliveryTracker;
use Pushery\VisualFeedback\Channels\Webhook\HttpWebhookSender;
use Pushery\VisualFeedback\Channels\Webhook\WebhookPayload;
use Pushery\VisualFeedback\Channels\Webhook\WebhooksPlatform;
use Pushery\VisualFeedback\Data\Report;
use RuntimeException;
use Throwable;

/**
 * The webhook channel's own queued job. Running the delivery off the submit
 * path keeps a slow or failing webhook from ever blocking the reporter, and gives the
 * channel independent queue/tries/backoff tuning (from `channels.webhook`). It carries only
 * queue-safe data — the Report DTO (plain scalars + small value objects, no Eloquent model)
 * plus the resolved retry knobs — so serialization is safe.
 *
 * Two delivery paths, chosen in deliver(): the pushery/webhooks platform when installed (it
 * owns signing/retry/dedupe), else the built-in signed HTTP sender. Both use the SAME
 * minimized, path-and-binary-free payload. On success handle() settles DELIVERED; a failure
 * throws so the queue retries, and once the retries in `channels.webhook` are exhausted the
 * failed() hook settles FAILED — both through the single ReportDeliveryTracker.
 */
final class SendReportWebhook implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Report $report,
        public readonly bool $includeReporter,
        public readonly int $tries,
        private readonly int $backoffSeconds,
    ) {}

    public function handle(WebhooksPlatform $platform, HttpWebhookSender $sender, Config $config, ReportDeliveryTracker $tracker): void
    {
        $this->deliver($platform, $sender, $config);

        $tracker->settleDelivered($this->report, 'webhook');
    }

    /** Retries exhausted → the ONE terminal failure path for this channel. */
    public function failed(Throwable $exception): void
    {
        Container::getInstance()->make(ReportDeliveryTracker::class)
            ->settleFailed($this->report, 'webhook', $exception);
    }

    /** Seconds between retries — the channel resolves it from `channels.webhook.backoff`. */
    public function backoff(): int
    {
        return $this->backoffSeconds;
    }

    private function deliver(WebhooksPlatform $platform, HttpWebhookSender $sender, Config $config): void
    {
        $payload = WebhookPayload::for($this->report, $this->includeReporter);

        // Fleet infrastructure first: the platform owns signing, retries and de-duplication.
        if ($platform->isInstalled()) {
            $reached = $platform->dispatch($payload);

            if ($reached === 0) {
                // NOT settleFailed(): nothing failed. The platform accepted the event and found
                // nobody subscribed to it, which is a configuration state, not an error — a retry
                // would produce the same zero, and a FAILED receipt would send an operator
                // looking for a broken endpoint that does not exist.
                //
                // But it must not be silent either, because a delivered receipt for zero
                // recipients is a receipt for nothing, and the two used to be indistinguishable.
                // Reaching zero takes only a fresh install: the event has to be listed in
                // `webhooks.platform.catalog` AND something has to subscribe to it.
                Container::getInstance()->make(LoggerInterface::class)->warning(
                    'visual-feedback: the webhooks platform matched no subscription for this report',
                    [
                        'event_type' => WebhooksPlatform::EVENT_TYPE,
                        'report' => $this->report->id,
                    ],
                );
            }

            return;
        }

        $url = $config->get('visual-feedback.webhook.url');

        // The channel's isAvailable() guarantees a URL when the platform is absent; a config
        // change between enqueue and run could still land here. Fail loudly — a silent return
        // would drop the report with no trace.
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('visual-feedback: webhook channel has no platform and no webhook.url configured.');
        }

        $secret = $config->get('visual-feedback.webhook.secret');

        // The same guarantee for the signing key, and it is the sharper of the two. An absent
        // secret does not break the signature — hash_hmac() gives a null and an empty key the
        // same digest — so the delivery would go out signed with a key everybody has, and the
        // published verification recipe would accept it. Refusing here settles the delivery as
        // failed instead of shipping an unauthenticated report.
        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('visual-feedback: webhook channel has no platform and no webhook.secret configured — a report is never signed with an empty key.');
        }

        // Sign the EXACT bytes that go on the wire (see WebhookSignature). The timestamp is
        // fresh per attempt, so a retry re-signs within its own replay window.
        //
        // JSON_THROW_ON_ERROR, and it is the whole point of this line rather than a nicety.
        // Without it json_encode returns false for a payload it cannot encode — a byte sequence
        // that is not valid UTF-8 — a `(string)` cast turned that into '', and the job then
        // delivered an EMPTY body, correctly signed, which the receiver accepted with 200 and the
        // report settled DELIVERED. Nothing logged, no event, no retry: the content was simply
        // gone and the maintainer was told it arrived. Throwing instead lets the queue retry and,
        // once the attempts are spent, `failed()` writes the FAILED receipt and fires
        // ReportDeliveryFailed — a maintainer sees that a report did not arrive.
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) Carbon::now()->getTimestamp();

        $sender->send($url, $this->report->id, $body, $timestamp);
    }
}
