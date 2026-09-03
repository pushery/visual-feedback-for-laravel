<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Submission;

use DateTimeImmutable;
use Pushery\VisualFeedback\Data\ReportContextEntry;
use Pushery\VisualFeedback\Data\Reporter;

/**
 * The transport-agnostic input to the submit pipeline. The Livewire component (or any
 * future frontend adapter) does the UI-bound work — temp uploads, screenshot capture,
 * storing files — and hands the service PATHS, so the service never touches a
 * TemporaryUploadedFile or any Livewire type. Attachments and the screenshot are
 * already-stored storage paths by the time they reach here.
 */
final readonly class SubmissionInput
{
    /**
     * @param  list<ReportContextEntry>  $context
     * @param  array<string, scalar|null>  $metadata
     * @param  list<string>  $attachmentPaths  already-stored storage paths
     * @param  array<string, mixed>  $challenge  client-supplied challenge data, UNTRUSTED and unenforced
     */
    public function __construct(
        public string $category,
        public ?string $subject,
        public string $message,
        public Reporter $reporter,
        public string $mode,
        public array $context = [],
        public array $metadata = [],
        public array $attachmentPaths = [],
        public ?string $screenshotPath = null,
        public string $honeypot = '',
        public ?string $ipAddress = null,
        public ?DateTimeImmutable $formOpenedAt = null,
        /** Per-call-site mail recipient, overriding `mail.to` for this report only. */
        public ?string $recipient = null,
        /**
         * Whatever a challenge widget put in the form — a Turnstile token, a proof-of-work nonce,
         * anything. It is carried, never interpreted: no shipped code reads a key out of it, and
         * the built-in floor ignores it entirely. The gate that asked for it is the only thing
         * that knows what it means, and the only thing that may trust it.
         *
         * APPENDED LAST on purpose. This constructor is the seam a future frontend adapter calls,
         * and inserting a parameter into the middle of it silently re-points every positional
         * argument after the insertion. `ReportAttempt` appends for the same reason.
         *
         * @var array<string, mixed>
         */
        public array $challenge = [],
    ) {}
}
