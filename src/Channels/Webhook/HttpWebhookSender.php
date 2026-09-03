<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels\Webhook;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * The built-in signed HTTP webhook sender — the hardened fallback used when the
 * pushery/webhooks platform is not installed. Hardening, not a note:
 *
 *  - sends ONLY to the configured `webhook.url` (never a caller/tenant-supplied URL);
 *  - REFUSES redirects — Laravel's client follows them by default, so a config URL could
 *    otherwise bounce onto an attacker-controlled host (a poor man's SSRF);
 *  - sets explicit connect + request timeouts from `webhook.timeout`;
 *  - REFUSES to sign with an absent or empty `webhook.secret` — an empty HMAC key produces a
 *    signature the whole internet can recompute, not a broken one (see secret());
 *  - signs the EXACT raw bytes it sends (withBody, not a re-encoded array), so a receiver
 *    recomputes over the body it actually received rather than over a re-serialization of
 *    it — note the MAC input is "{timestamp}.{rawBody}", not the body alone;
 *  - ignores the response body.
 *
 * Per-tenant / dynamic-URL SSRF hardening (scheme allowlist, private / link-local IP
 * rejection) is a HOST responsibility for a host that overrides the URL per tenant — it is
 * documented, deliberately not attempted here for a single static config URL.
 */
final readonly class HttpWebhookSender
{
    public function __construct(
        private HttpFactory $http,
        private Config $config,
    ) {}

    /**
     * The fully-configured, signed request — built but NOT sent. This is the testable seam:
     * Http::fake() cannot prove redirect refusal (it ignores redirect options), so the
     * no-redirect + timeout guarantees are asserted on getOptions() of THIS request instead.
     */
    public function request(string $reportId, string $body, string $timestamp): PendingRequest
    {
        $timeout = $this->timeout();

        return $this->http
            ->withoutRedirecting()
            ->connectTimeout($timeout)
            ->timeout($timeout)
            ->withHeaders([
                'Accept' => 'application/json',
                WebhookSignature::TIMESTAMP_HEADER => $timestamp,
                WebhookSignature::SIGNATURE_HEADER => WebhookSignature::sign($this->secret(), $timestamp, $body),
                WebhookSignature::ID_HEADER => $reportId,
            ]);
    }

    /**
     * Deliver the signed body. A non-2xx response (or a connect/read timeout) throws, which
     * propagates out of the queued job so the queue retry / terminal-failure path runs.
     */
    public function send(string $url, string $reportId, string $body, string $timestamp): void
    {
        $this->request($reportId, $body, $timestamp)
            ->withBody($body, 'application/json')
            ->post($url)
            ->throw();
    }

    /**
     * The signing key — REFUSED rather than substituted when it is absent, blank or not a string.
     *
     * This used to fall back to '', which reads like a harmless default and is the opposite of
     * one. `hash_hmac()` produces the same digest for a null key and an empty string, and every
     * other input to the MAC is public: the algorithm, the "{timestamp}.{rawBody}" format and the
     * header names are constants in WebhookSignature and printed on the documentation site. An
     * empty key therefore yields a signature that verifies — for anybody who knows the endpoint
     * URL. It is the one failure mode where sending nothing is strictly better than sending.
     */
    private function secret(): string
    {
        $secret = $this->config->get('visual-feedback.webhook.secret');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('visual-feedback: webhook.secret is missing or empty — refusing to sign a report with an empty HMAC key.');
        }

        return $secret;
    }

    private function timeout(): int
    {
        $timeout = $this->config->get('visual-feedback.webhook.timeout');

        return is_numeric($timeout) && (int) $timeout > 0 ? (int) $timeout : 5;
    }
}
