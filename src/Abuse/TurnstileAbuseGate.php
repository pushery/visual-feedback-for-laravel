<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Abuse;

use Illuminate\Http\Client\Factory as HttpFactory;
use Pushery\VisualFeedback\Contracts\AbuseGate;
use Pushery\VisualFeedback\Events\RejectionReason;
use Pushery\VisualFeedback\Support\Settings;
use RuntimeException;

/**
 * Verifies the token of a Cloudflare Turnstile widget with Cloudflare's siteverify endpoint.
 *
 * This is the additional driver `turnstile`, which the package registers and which `abuse.driver`
 * selects by itself once both keys under `abuse.turnstile` are set. The widget renders inside the
 * form and puts its token into `challenge.token`. This gate sends the token, the secret key and the
 * reporter's address to Cloudflare and lets the report through on a success for the widget's action.
 *
 * A missing token, one that is not a string or one longer than Cloudflare accepts is refused
 * without a request, and so is a token Cloudflare does not accept. A person can fail both, by
 * sending before the widget answered or after its token expired, so the refusal is visible and
 * asks the reporter to try again. A failure that is not the reporter's is thrown instead: a secret
 * key that is missing or that Cloudflare does not know, an answer that carries no verdict, or no
 * answer at all. `abuse.drivers.turnstile.on_error`, or `abuse.driver_on_error`, then decides
 * whether the report goes through on the floor alone.
 */
final readonly class TurnstileAbuseGate implements AbuseGate
{
    /** Cloudflare's endpoint that verifies a token on the server. */
    public const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** The action the shipped widget names, held against the verdict so that a token issued for another widget on the same site key is refused. */
    public const string ACTION = 'visual-feedback';

    /** The longest token siteverify accepts. */
    private const int MAX_TOKEN_LENGTH = 2048;

    /** The error codes that blame the configuration or Cloudflare itself rather than the reporter. */
    private const array NOT_THE_REPORTERS = ['missing-input-secret', 'invalid-input-secret', 'internal-error'];

    public function __construct(
        private HttpFactory $http,
        private Settings $settings,
    ) {}

    public function check(ReportAttempt $attempt): AbuseDecision
    {
        $token = $attempt->challenge['token'] ?? null;

        if (! is_string($token) || $token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return AbuseDecision::reject(RejectionReason::ChallengeFailed, visible: true, detail: 'turnstile_no_token');
        }

        $secret = $this->settings->turnstileSecretKey();

        if ($secret === null) {
            throw new RuntimeException('The Turnstile driver is selected, and abuse.turnstile.secret_key is not set.');
        }

        $fields = ['secret' => $secret, 'response' => $token];

        if (is_string($attempt->ipAddress) && $attempt->ipAddress !== '') {
            $fields['remoteip'] = $attempt->ipAddress;
        }

        $verdict = $this->http->asForm()
            ->connectTimeout(3)
            ->timeout(5)
            ->post(self::VERIFY_URL, $fields)
            ->throw()
            ->json();

        if (! is_array($verdict) || ! is_bool($verdict['success'] ?? null)) {
            throw new RuntimeException('Cloudflare answered siteverify without a verdict.');
        }

        $codes = is_array($verdict['error-codes'] ?? null) ? $verdict['error-codes'] : [];
        $blamed = array_values(array_filter(
            self::NOT_THE_REPORTERS,
            static fn (string $code): bool => in_array($code, $codes, true),
        ));

        if ($blamed !== []) {
            throw new RuntimeException('Cloudflare refused to verify the Turnstile token: '.implode(', ', $blamed).'.');
        }

        // Only a token issued for this form's action passes. The shipped view always names it, so a
        // verdict with another action, an empty one or none comes from a widget not rendered for this form.
        $action = $verdict['action'] ?? null;

        if (! $verdict['success'] || $action !== self::ACTION) {
            return AbuseDecision::reject(RejectionReason::ChallengeFailed, visible: true, detail: 'turnstile_refused');
        }

        return AbuseDecision::allow();
    }
}
