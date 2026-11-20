<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Standalone;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;
use RuntimeException;

/**
 * The form token of the standalone widget: the moment its form was opened, signed with the application key.
 *
 * It does for a page without a session what FormOpenedAt does for the Livewire widget. The widget asks for a token
 * when the reporter opens the form, and the submission carries it back, so the time trap measures from the opening,
 * on the server's own clock. The signature keeps a client from moving that moment. A token older than
 * `standalone.token_ttl` seconds, one from the future, or one this application did not sign opens nothing.
 *
 * Asking for a fresh token is what opening the form is, and a later moment only makes the trap stricter.
 */
final readonly class StandaloneToken
{
    public function __construct(private Repository $config) {}

    /** A token for a form opened at $openedAt, a Unix timestamp from the server clock. */
    public function issue(int $openedAt): string
    {
        $payload = $openedAt.'.'.bin2hex(random_bytes(8));

        return $payload.'.'.$this->sign($payload);
    }

    /** The opening moment the token carries, or null for a token that is forged, malformed, from the future or expired. */
    public function openedAt(string $token, int $now): ?DateTimeImmutable
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3 || preg_match('/^\d+\z/', $parts[0]) !== 1 || ! $this->signedHere($parts[0].'.'.$parts[1], $parts[2])) {
            return null;
        }

        $openedAt = (int) $parts[0];

        return $openedAt > $now || $now - $openedAt > $this->ttl() ? null : new DateTimeImmutable('@'.$openedAt);
    }

    /**
     * Whether the signature is one this application made, under its key or one of `app.previous_keys`. Laravel takes
     * those for its own signatures as well, so a form a reporter opened before the key was rotated still sends while
     * the old key stays listed.
     */
    private function signedHere(string $payload, string $signature): bool
    {
        return array_any([$this->key(), ...$this->previousKeys()], fn (string $key): bool => hash_equals($this->signWith($payload, $key), $signature));
    }

    private function sign(string $payload): string
    {
        return $this->signWith($payload, $this->key());
    }

    private function signWith(string $payload, string $key): string
    {
        return hash_hmac('sha256', 'visual-feedback.standalone|'.$payload, $key);
    }

    private function key(): string
    {
        $key = $this->config->get('app.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('The standalone widget signs its form tokens with the application key, and app.key is empty.');
        }

        return $key;
    }

    /**
     * The keys the application rotated away from and still accepts, as `config/app.php` lists them.
     *
     * @return list<string>
     */
    private function previousKeys(): array
    {
        $listed = $this->config->get('app.previous_keys');
        $keys = [];

        foreach (is_array($listed) ? $listed : [] as $key) {
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function ttl(): int
    {
        $ttl = $this->config->get('visual-feedback.standalone.token_ttl');

        return is_numeric($ttl) && (int) $ttl > 0 ? (int) $ttl : 7200;
    }
}
