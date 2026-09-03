<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Data;

use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * The immutable report value object — the single hand-off format to channels and
 * events. It carries its own identity: a UUID assigned once at submission and stable
 * across the whole delivery lifecycle. That UUID is the idempotency key for delivery
 * receipts, the database upsert, and webhook de-duplication — an at-least-once queue
 * without a stable identity produces duplicate deliveries.
 *
 * Everything it holds is queue-serialization-safe: scalars, arrays, and small value
 * objects. No closures, no Eloquent models, and attachments are storage PATHS, never a
 * `TemporaryUploadedFile` — the upload pipeline stores first and passes paths, and an
 * architecture test holds that boundary.
 *
 * `category` is a config key; its human label is resolved from `lang`, so a mailable
 * never has to read categories back off a domain class.
 */
final readonly class Report
{
    /**
     * @param  list<ReportContextEntry>  $context
     * @param  list<string>  $attachments  attachment storage paths
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public string $id,
        public string $category,
        public ?string $subject,
        public string $message,
        public Reporter $reporter,
        public array $context,
        public array $attachments,
        public array $metadata,
        public string $mode,
        public DateTimeImmutable $submittedAt,
        /**
         * Where the mail channel should send THIS report, overriding `mail.to`. Deliberately
         * absent from toArray(): it is delivery routing, not report content, and the webhook
         * payload and the database row must not carry a maintainer address.
         */
        public ?string $recipient = null,
    ) {}

    /**
     * Build a report for a fresh submission, assigning its stable UUID.
     *
     * @param  list<ReportContextEntry>  $context
     * @param  list<string>  $attachments
     * @param  array<string, scalar|null>  $metadata
     */
    public static function forSubmission(
        string $category,
        ?string $subject,
        string $message,
        Reporter $reporter,
        array $context,
        array $attachments,
        array $metadata,
        string $mode,
        DateTimeImmutable $submittedAt,
        ?string $recipient = null,
    ): self {
        return new self(
            id: Str::uuid()->toString(),
            category: $category,
            subject: $subject,
            message: $message,
            reporter: $reporter,
            context: $context,
            attachments: $attachments,
            metadata: $metadata,
            mode: $mode,
            submittedAt: $submittedAt,
            recipient: $recipient,
        );
    }

    /**
     * @return array{
     *     id: string,
     *     category: string,
     *     subject: ?string,
     *     message: string,
     *     reporter: array{id: ?string, name: ?string, email: ?string, is_guest: bool, phone: ?string},
     *     context: list<array{key: string, label: string, value: string, url: ?string, identifier: ?string}>,
     *     attachments: list<string>,
     *     metadata: array<string, scalar|null>,
     *     mode: string,
     *     submitted_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'subject' => $this->subject,
            'message' => $this->message,
            'reporter' => $this->reporter->toArray(),
            'context' => array_map(static fn (ReportContextEntry $entry): array => $entry->toArray(), $this->context),
            'attachments' => $this->attachments,
            'metadata' => $this->metadata,
            'mode' => $this->mode,
            'submitted_at' => $this->submittedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
