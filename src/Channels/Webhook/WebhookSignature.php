<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels\Webhook;

/**
 * The signed-webhook contract for the built-in HTTP fallback. The signature
 * binds the send-time timestamp INTO the HMAC, so a captured body cannot be replayed under a
 * fresh timestamp, and it is computed over the EXACT raw bytes that are sent (never a
 * re-encoded array — that is the classic webhook-signature break). A receiver reproduces it
 * with the shared secret and compares with hash_equals(), then rejects any timestamp outside
 * its own replay window. Header names + algorithm are constants here so the documented
 * verification example and the sender can never drift apart.
 */
final class WebhookSignature
{
    /** The signing algorithm — documented so a receiver can reproduce it exactly. */
    public const string ALGORITHM = 'sha256';

    /** Hex HMAC of "{timestamp}.{rawBody}" travels in this header. */
    public const string SIGNATURE_HEADER = 'X-Visual-Feedback-Signature';

    /** The send-time Unix timestamp the signature is bound to (the replay-window anchor). */
    public const string TIMESTAMP_HEADER = 'X-Visual-Feedback-Timestamp';

    /** The report UUID — the receiver's idempotency / de-duplication key. */
    public const string ID_HEADER = 'X-Visual-Feedback-Id';

    /**
     * HMAC over "{timestamp}.{rawBody}", hex-encoded. Deterministic for a fixed
     * (secret, timestamp, body) triple, which is what makes receiver-side verification
     * a plain recompute-and-compare.
     */
    public static function sign(string $secret, string $timestamp, string $body): string
    {
        return hash_hmac(self::ALGORITHM, $timestamp.'.'.$body, $secret);
    }
}
