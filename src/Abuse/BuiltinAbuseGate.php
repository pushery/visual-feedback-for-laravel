<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Abuse;

use Illuminate\Cache\RateLimiter;
use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Contracts\AbuseGate;
use Pushery\VisualFeedback\Events\RejectionReason;
use Pushery\VisualFeedback\Support\Settings;
use Throwable;

/**
 * The always-on abuse floor: a filled honeypot, a too-fast fill,
 * and a per-hour rate limit — none of which cost a real human anything. This runs under EVERY
 * driver (beneath botgate, not instead of it), so a challenge-provider outage or a
 * misconfiguration can never remove all protection.
 *
 * The rate limiter is hit on EVERY attempt, before any other verdict — so a failing attempt
 * (honeypot, too-fast, later a validation error) still burns a token. Hitting the
 * limiter only after a successful store — the obvious ordering — lets an attacker make unlimited
 * failing attempts at full server cost.
 *
 * `on_error` applies ONLY here (the builtin path) and ONLY to the limiter, which is the only
 * part of this gate that does I/O. `closed` refuses the submission when the limiter backend
 * errors; `open` — the SHIPPED default — lets it past the LIMIT, and the honeypot and the time
 * trap still run.
 *
 * That last clause is the whole point, and it is why the catch is scoped to the limiter call
 * alone. A catch around the entire evaluation would discard the honeypot and time-trap verdicts
 * along with the rate limit: under `open` a bot filling the honeypot gets through, and under
 * `closed` every human is refused. Neither is a floor. Both checks are pure — no cache, no disk,
 * no network — so nothing about a broken limiter should reach them.
 *
 * With that scope, `open` is defensible and is what ships: a cache outage does not remove
 * protection, it removes one hour's counting.
 */
final readonly class BuiltinAbuseGate implements AbuseGate
{
    private const int DECAY_SECONDS = 3600;

    public function __construct(
        private RateLimiter $limiter,
        private Settings $settings,
        private LoggerInterface $logger,
    ) {}

    public function check(ReportAttempt $attempt): AbuseDecision
    {
        // The rate limit is the only part of this gate that can fail, so it is the only part
        // wrapped. Everything after it is pure and runs whatever the cache is doing.
        $limit = $this->rateLimitVerdict($attempt);

        if ($limit instanceof AbuseDecision) {
            return $limit;
        }

        // The silent bot floor: a filled honeypot or a fill faster than a human could manage.
        // No cache, no disk, no network — there is nothing here for an outage to break, and
        // nothing above may discard either verdict on a broken limiter's behalf.
        // THREE CAUSES SHARE ONE REASON, AND ONE OF THEM IS NOT A BOT. `RejectionReason::
        // Honeypot` is what a host's listener sees for a filled honeypot, for a fill faster than
        // a human manages, AND for a submission that carries no open time at all -- and the third
        // is a broken INTEGRATION, not traffic: an adapter that never stamps the open time turns
        // every submission into a decoy success, silently, and the only signal the host gets
        // reads like bot volume.
        //
        // The enum stays as it is -- it is small on purpose and a new case is an API change --
        // and `detail` carries the distinction instead. It costs a listener nothing to ignore.
        if ($attempt->honeypot !== '') {
            return AbuseDecision::reject(RejectionReason::Honeypot, detail: 'honeypot');
        }

        $minimum = $this->settings->minFillSeconds();
        $elapsed = $attempt->elapsedSeconds();

        // FAIL-CLOSED on a missing open time, and this is the half that has to move together
        // with the widget: a submission that cannot say when its form was opened is not a
        // submission the trap can judge, so while the trap is armed it is refused rather than
        // waved through. Read the other way round — null meaning "the trap does not apply" —
        // an attacker skips the trap entirely by omitting one field.
        //
        // `$minimum > 0` is the whole condition, because `min_fill_seconds = 0` is the
        // documented way to switch the trap OFF. A host that turned it off must not start
        // getting rejections from it.
        if ($minimum > 0 && $elapsed === null) {
            return AbuseDecision::reject(RejectionReason::Honeypot, detail: 'open_time_missing');
        }

        if ($elapsed !== null && $elapsed < $minimum) {
            return AbuseDecision::reject(RejectionReason::Honeypot, detail: 'too_fast');
        }

        return AbuseDecision::allow();
    }

    /**
     * The rate limit's verdict, or null when the attempt is under the limit and the rest of the
     * floor should decide.
     *
     * Counts THIS attempt first — even one about to be rejected — so failing attempts still burn
     * a token. A backend error is resolved HERE rather than by an outer catch, because an outer
     * catch cannot tell "the limiter is down" from "the honeypot check threw" and therefore has
     * to throw both verdicts away.
     */
    private function rateLimitVerdict(ReportAttempt $attempt): ?AbuseDecision
    {
        $max = $attempt->reporter->isGuest ? $this->settings->guestRateLimit() : $this->settings->rateLimit();

        try {
            $hits = $this->limiter->hit($this->rateLimitKey($attempt), self::DECAY_SECONDS);
        } catch (Throwable $exception) {
            $this->logger->warning('visual-feedback: builtin rate limiter errored', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                // Named so an operator reading the log knows which half of the floor was lost.
                'floor' => 'rate_limit_only',
            ]);

            return $this->settings->abuseOpensOnError()
                ? null                                              // past the LIMIT, not past the floor
                : AbuseDecision::reject(RejectionReason::RateLimited, visible: true);
        }

        return $hits > $max ? AbuseDecision::reject(RejectionReason::RateLimited, visible: true) : null;
    }

    private function rateLimitKey(ReportAttempt $attempt): string
    {
        if ($attempt->reporter->isGuest) {
            // Per guest IP; hashed so an IPv6's colons stay cache-key-safe and no raw IP is stored.
            return 'visual-feedback:abuse:guest:'.hash('sha256', $this->rateLimitSubject($attempt->ipAddress));
        }

        return 'visual-feedback:abuse:user:'.($attempt->reporter->id ?? 'unknown');
    }

    /**
     * The address the guest bucket is keyed on: IPv4 whole, IPv6 truncated to its /64.
     *
     * THE FULL IPv6 ADDRESS MADE THE GUEST LIMIT MEANINGLESS, AND IT COST AN ATTACKER NOTHING.
     * A residential IPv6 assignment is at least a /64 -- 2^64 addresses the same person may use --
     * and changing the interface identifier per request needs no proxy, no botnet and no
     * cooperation from anyone: one `ip addr add` and every request lands in its own 5/hour bucket.
     * Measured before the change: `2001:db8:1:2::1`, `::2` and `::ffff:ffff:ffff:ffff` hashed to
     * three unrelated keys while sharing one /64.
     *
     * /64 is the boundary because it is the boundary the internet hands out. Truncating further
     * would fold unrelated customers of one ISP into a shared bucket; truncating less is what this
     * did. IPv4 is left whole -- a /24 there is a neighborhood, not a household.
     *
     * A malformed or absent address keeps its old behavior and shares the `unknown` bucket, which
     * is deliberate: an adapter that cannot say who is calling gets the strictest treatment
     * available, not an exemption.
     */
    private function rateLimitSubject(?string $ipAddress): string
    {
        if ($ipAddress === null || ! str_contains($ipAddress, ':')) {
            return $ipAddress ?? 'unknown';
        }

        $packed = @inet_pton($ipAddress);

        if ($packed === false || strlen($packed) !== 16) {
            return $ipAddress;
        }

        $network = inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8));

        return $network === false ? $ipAddress : $network.'/64';
    }
}
