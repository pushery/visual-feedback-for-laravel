<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionResolverInterface as ConnectionResolver;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Pushery\VisualFeedback\Channels\ReceiptStore;
use Pushery\VisualFeedback\Channels\ReportDeliveryTracker;
use Pushery\VisualFeedback\Data\Report;
use Throwable;

/**
 * The database channel's own queued job. The row write runs off the submit
 * path — so a slow or contended database never blocks the reporter — and settles through the
 * single ReportDeliveryTracker like every other channel. The write is an UPSERT keyed on the
 * report UUID, so an at-least-once queue retry never creates a second row.
 */
final class SendReportToDatabase implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Report $report,
        public readonly int $tries,
        private readonly int $backoffSeconds,
    ) {}

    public function handle(Config $config, ConnectionResolver $db, ReceiptStore $receipts, ReportDeliveryTracker $tracker): void
    {
        $serialized = $this->report->toArray();
        $metadata = $this->report->metadata;
        $userAgent = $metadata['user_agent'] ?? null;

        $db->connection()->table($this->table($config))->upsert(
            [[
                'uuid' => $this->report->id,
                'mode' => $this->report->mode,
                'category' => $this->report->category,
                'subject' => $this->report->subject,
                'message' => $this->report->message,
                'reporter_id' => $this->report->reporter->id,
                'reporter_name' => $this->report->reporter->name,
                'reporter_email' => $this->report->reporter->email,
                'reporter_phone' => $this->report->reporter->phone,
                'is_guest' => $this->report->reporter->isGuest,
                'context' => $this->json($serialized['context']),
                'metadata' => $this->json($metadata),
                'attachments' => $this->json($this->report->attachments),
                'deliveries' => $this->json($this->deliveryMap($receipts)),
                'user_agent' => is_string($userAgent) ? mb_substr($userAgent, 0, 500) : null,
                'created_at' => Carbon::instance(Carbon::parse($this->report->submittedAt->format(DATE_ATOM))),
                'updated_at' => Carbon::now(),
            ]],
            uniqueBy: ['uuid'],
            update: ['deliveries', 'updated_at'],
        );

        $tracker->settleDelivered($this->report, 'database');
    }

    /** Retries exhausted → the ONE terminal failure path for this channel. */
    public function failed(Throwable $exception): void
    {
        Container::getInstance()->make(ReportDeliveryTracker::class)
            ->settleFailed($this->report, 'database', $exception);
    }

    public function backoff(): int
    {
        return $this->backoffSeconds;
    }

    /** @return array<string, string> the receipt map, as plain channel → status strings. */
    private function deliveryMap(ReceiptStore $receipts): array
    {
        $map = [];

        foreach ($receipts->receipts($this->report->id) as $channel => $status) {
            $map[$channel] = $status->value;
        }

        return $map;
    }

    /**
     * JSON_THROW_ON_ERROR, for the same reason the webhook job carries it: without it
     * json_encode returns false for a value it cannot encode and the `(string)` cast wrote `''`
     * into the column. The row then looked intact — message and reporter columns carry the
     * report — while the JSON column held an empty string, which on MySQL's real `json` type is
     * not even a legal value. Throwing lets the queue retry and settle FAILED, which is a state
     * somebody can see.
     */
    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function table(Config $config): string
    {
        $table = $config->get('visual-feedback.database.table');

        return is_string($table) && $table !== '' ? $table : 'visual_feedback_reports';
    }
}
