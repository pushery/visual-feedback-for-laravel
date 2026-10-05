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

    /**
     * Routes whose path is a credential, as `metadata.url_token_paths` ships them: Laravel's
     * password reset and email verification, Laravel UI's password reset, the magic links of
     * pushery/email-magic-link-for-laravel and Jetstream's team invitations.
     *
     * The config file reads this list, and so does the sanitizer when the key is missing: a cached
     * configuration built from a file published before the key existed never gets the default
     * filled in.
     */
    public const array TOKEN_PATHS = [
        'reset-password/*',
        'password/reset/*',
        'email/verify/*/*',
        'magic-link/verify/*',
        'team-invitations/*',
    ];

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

                // A referrer is reduced to its origin, and this is enforcement rather than
                // tidiness. Under `Referrer-Policy: strict-origin-when-cross-origin` — the
                // browsers' default — a same-origin navigation sends the full URL, path included. In a
                // Laravel application the path is routinely the credential itself:
                // `reset-password/{token}`, `email/verify/{id}/{hash}`, a magic link. The page
                // somebody lands on after one of those is exactly where they file a report.
                //
                // The key is out of the shipped `collect` list for that reason. This line is what
                // holds when a consumer puts it back — and they will, because it reads like
                // `language` or `platform` in a list of context fields. The origin answers what
                // the field is for ("they came from our marketing site"); the path only ever adds
                // somebody else's secret.
                //
                // `url` is not cut to its origin: that one is the report's subject, the reporter
                // chose to file from there, and its path is the diagnosis. It loses what can be a
                // credential instead, below.
                if ($key === 'referrer') {
                    $parts = parse_url($value);
                    $host = is_array($parts) ? ($parts['host'] ?? null) : null;

                    if (! is_string($host) || $host === '') {
                        continue;
                    }

                    $value = strtolower((string) ($parts['scheme'] ?? 'https')).'://'.$host
                        .(isset($parts['port']) ? ':'.$parts['port'] : '');
                }

                if ($key === 'url') {
                    $value = $this->reportedUrl($value);

                    if ($value === null) {
                        continue;
                    }
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
     * The note that stood here said json_encode "throws on every retry and wedges the
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
     * The page a report was filed from, without what can be somebody's credential.
     *
     * The path is kept, because it is what a bug report is about. In a Laravel application it can
     * also be the credential itself: `reset-password/{token}` is a page somebody files a report
     * from, and its token resets the account for anybody who reads the report. So each segment a
     * route in `metadata.url_token_paths` marks with `*` becomes `{token}`, wherever the route sits
     * in the path, behind a locale or a mount prefix too. The fragment and any user info always go.
     * The query goes unless `metadata.url_query` is on: a signed URL, an `?email=` and a search
     * term live there.
     */
    private function reportedUrl(string $value): ?string
    {
        $parts = parse_url($value);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if (! is_array($parts) || ! is_string($host) || $host === '') {
            return null;
        }

        $query = (string) ($parts['query'] ?? '');
        $keepsQuery = filter_var($this->config->get('visual-feedback.metadata.url_query', false), FILTER_VALIDATE_BOOLEAN);

        return strtolower((string) ($parts['scheme'] ?? 'https')).'://'.$host
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .$this->maskTokenPaths((string) ($parts['path'] ?? ''))
            .($keepsQuery && $query !== '' ? '?'.$query : '');
    }

    private function maskTokenPaths(string $path): string
    {
        $segments = explode('/', $path);

        foreach ($this->tokenPaths() as $route) {
            $pattern = array_values(array_filter(explode('/', $route), static fn (string $segment): bool => $segment !== ''));

            for ($start = 0; $pattern !== [] && $start + count($pattern) <= count($segments); $start++) {
                if (! $this->routeMatchesAt($segments, $pattern, $start)) {
                    continue;
                }

                foreach ($pattern as $offset => $expected) {
                    if ($expected === '*') {
                        $segments[$start + $offset] = '{token}';
                    }
                }
            }
        }

        return implode('/', $segments);
    }

    /**
     * @param  array<int, string>  $segments
     * @param  list<string>  $pattern
     */
    private function routeMatchesAt(array $segments, array $pattern, int $start): bool
    {
        foreach ($pattern as $offset => $expected) {
            $segment = $segments[$start + $offset];

            if ($expected === '*' ? $segment === '' : strcasecmp($segment, $expected) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function tokenPaths(): array
    {
        // Missing means the default. An empty list is a host switching the masking off.
        $configured = $this->config->get('visual-feedback.metadata.url_token_paths');

        return array_values(array_filter(
            is_array($configured) ? $configured : self::TOKEN_PATHS,
            is_string(...),
        ));
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
