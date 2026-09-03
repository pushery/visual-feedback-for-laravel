<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Metadata;

use Illuminate\Contracts\Config\Repository;

/**
 * Reduces raw, client-collected browser metadata to the server-enforced safe subset.
 * The reporter's browser sends this, so it is UNTRUSTED — this is the enforcement, not
 * a suggestion. It keeps only the configured allowlist of keys, only scalar values,
 * scrubs invalid UTF-8 (so a later json_encode in the delivery job can never throw),
 * accepts only http/https for URL-shaped keys, truncates every string to its cap (a
 * separate, tighter cap for the user agent), lets the SERVER's user agent override the
 * client's, and NEVER lets the IP address through.
 */
final readonly class MetadataSanitizer
{
    /**
     * Metadata keys the server owns outright. Never accepted from the client, whatever the
     * consuming application's allowlist says.
     */
    public const string RESERVED_PREFIX = 'privacy_notice_';

    /** URL-shaped keys: only an http(s) value survives; anything else (javascript:, data:) is dropped. */
    private const array URL_KEYS = ['url', 'referrer'];

    public function __construct(private Repository $config) {}

    /**
     * @param  array<string, mixed>  $raw  untrusted, client-collected metadata
     * @param  string|null  $serverUserAgent  the request's own user agent; overrides the client's when the key is allowed
     * @return array<string, scalar|null>
     */
    public function sanitize(array $raw, ?string $serverUserAgent = null): array
    {
        $maxLength = $this->configInt('visual-feedback.metadata.max_value_length', 2_000);
        $userAgentMax = $this->configInt('visual-feedback.metadata.user_agent_max', 512);

        $clean = [];

        foreach ($this->allowedKeys() as $key) {
            // RESERVED keys are the server's alone. `privacy_notice_*` records which published
            // legal document an acknowledgment belongs to, and it is written after this method
            // returns, from a server-side read. Stripped here unconditionally rather than relying
            // on the allowlist: the allowlist belongs to the CONSUMING application, so a consumer
            // that adds one of these keys to `metadata.collect` would otherwise let a browser
            // supply its own provenance — and a forged one would look exactly like a real one.
            if (str_starts_with($key, self::RESERVED_PREFIX)) {
                continue;
            }

            // The IP address is never collected or stored, even if a consumer mistakenly
            // adds it to the allowlist.
            if ($key === 'ip') {
                continue;
            }

            // The user agent is server-authoritative when we have the request's own value:
            // a client can spoof its UA string, the transport layer cannot.
            if ($key === 'user_agent' && $serverUserAgent !== null) {
                $clean[$key] = mb_substr($this->scrubUtf8($serverUserAgent), 0, $userAgentMax);

                continue;
            }

            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $value = $raw[$key];

            if ($value !== null && ! is_scalar($value)) {
                continue;
            }

            if (is_string($value)) {
                $value = $this->scrubUtf8($value);

                // A URL-shaped key that is not an http(s) URL is dropped entirely, so a
                // `javascript:`/`data:` payload can never ride into the stored report.
                if (in_array($key, self::URL_KEYS, true) && preg_match('#^https?://#i', $value) !== 1) {
                    continue;
                }

                $value = mb_substr($value, 0, $key === 'user_agent' ? $userAgentMax : $maxLength);
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * Drop invalid UTF-8 byte sequences.
     *
     * This is the EARLY fix, and it is the right place for the one input that reaches it: the
     * `User-Agent` header, the only value on the widget's path that is not already carried
     * through Livewire's own JSON transport.
     *
     * ⚠️ The note that stood here said json_encode "throws on every retry and wedges the
     * channel". It did neither — it returned false, a `(string)` cast made that `''`, and the
     * delivery went out empty and settled DELIVERED. A reader who believed the old sentence would
     * have concluded the downstream was loud and this scrub redundant. Both jobs now pass
     * JSON_THROW_ON_ERROR, so an unencodable report settles FAILED rather than silently empty —
     * and this scrub still belongs here, because failing early beats failing at the far end.
     */
    private function scrubUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    /**
     * @return list<string>
     */
    private function allowedKeys(): array
    {
        $configured = $this->config->get('visual-feedback.metadata.collect');

        return array_values(array_filter(
            is_array($configured) ? $configured : [],
            is_string(...),
        ));
    }

    private function configInt(string $key, int $default): int
    {
        $value = $this->config->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
