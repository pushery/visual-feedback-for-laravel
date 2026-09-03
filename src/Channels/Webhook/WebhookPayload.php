<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels\Webhook;

use DateTimeImmutable;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Data\ReportContextEntry;

/**
 * The webhook payload — a MINIMIZED, external-safe projection of a Report.
 * The receiver is potentially a third party, so this DELIBERATELY omits the report's
 * `attachments` (storage paths into the host's private disk) and carries no file binaries:
 * nothing here is a private path or an attachment blob. The reporter block is configurably
 * reduced (`webhook.include_reporter`) to just `is_guest` when the host does not want
 * reporter PII leaving the app. Used by BOTH delivery paths (platform + HTTP fallback), so
 * the minimization holds regardless of transport.
 */
final readonly class WebhookPayload
{
    /**
     * @return array{
     *     id: string,
     *     category: string,
     *     subject: ?string,
     *     message: string,
     *     mode: string,
     *     submitted_at: string,
     *     context: list<array{key: string, label: string, value: string, url: ?string, identifier: ?string}>,
     *     metadata: array<string, scalar|null>,
     *     reporter: array{id: ?string, name: ?string, email: ?string, is_guest: bool, phone: ?string}|array{is_guest: bool}
     * }
     */
    public static function for(Report $report, bool $includeReporter): array
    {
        return [
            'id' => $report->id,
            'category' => $report->category,
            'subject' => $report->subject,
            'message' => $report->message,
            'mode' => $report->mode,
            'submitted_at' => $report->submittedAt->format(DateTimeImmutable::ATOM),
            'context' => array_map(
                static fn (ReportContextEntry $entry): array => $entry->toArray(),
                $report->context,
            ),
            'metadata' => $report->metadata,
            // Full reporter, or reduced to is_guest only — never more than the host allows off-site.
            'reporter' => $includeReporter
                ? $report->reporter->toArray()
                : ['is_guest' => $report->reporter->isGuest],
            // NO 'attachments' key — storage paths / binaries never leave in a webhook body.
        ];
    }
}
