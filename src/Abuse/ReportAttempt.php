<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Abuse;

use DateTimeImmutable;
use Pushery\VisualFeedback\Data\Reporter;

/**
 * Everything an AbuseGate needs to judge a single submit attempt, and nothing
 * more — it is transport-agnostic like the rest of the pipeline, so the Livewire component
 * resolves the request-bound pieces (IP, the honeypot value) and hands them in as plain data.
 *
 * `formOpenedAt` is the SERVER-anchored time the form was opened (the widget stamps it on mount,
 * inside Livewire's checksum-protected state, so a bot cannot forge a slow fill). The time trap
 * compares elapsedSeconds() against the configured minimum; a null open time means it was not
 * tracked, and the trap simply does not apply rather than guessing.
 */
final readonly class ReportAttempt
{
    public function __construct(
        public Reporter $reporter,
        public string $honeypot,
        public ?string $ipAddress,
        public ?DateTimeImmutable $formOpenedAt,
        public DateTimeImmutable $submittedAt,
        /**
         * Whatever a challenge widget put in the form, verbatim and UNTRUSTED — a Turnstile token,
         * a proof-of-work nonce, whatever the gate that rendered the widget asked for. Empty on
         * every install that configures no challenge view.
         *
         * The built-in floor never reads it. Nothing in this package reads a key out of it. It
         * exists so a gate CAN be interactive: without it, `extendAbuse()` could only ever carry
         * gates judging identity, origin and timing, because the form submits through Livewire and
         * a token in the markup is otherwise reachable only as a property of a component this
         * package owns.
         *
         * Typed as `mixed` rather than `scalar|null` deliberately. The value is hydrated straight
         * out of a Livewire payload, so a client can nest arrays inside it and nothing here
         * coerces them. A narrower annotation would be a promise the runtime does not keep — and
         * PHPStan HONORS it, so a gate author would be handed a static guarantee that a request
         * can violate. `ReportWidget::$metadata` carries the wide type for the same reason; the
         * difference is that metadata is narrowed by a sanitizer, and this is not, because the
         * package is only the courier here.
         *
         * @var array<string, mixed>
         */
        public array $challenge = [],
    ) {}

    /** Seconds the reporter spent on the form, or null when the open time was not tracked. */
    public function elapsedSeconds(): ?int
    {
        if (! $this->formOpenedAt instanceof DateTimeImmutable) {
            return null;
        }

        return $this->submittedAt->getTimestamp() - $this->formOpenedAt->getTimestamp();
    }
}
