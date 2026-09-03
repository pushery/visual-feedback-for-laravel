<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Schema;
use Pushery\VisualFeedback\Contracts\ReportChannel;
use Pushery\VisualFeedback\Contracts\RetainsReport;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Jobs\SendReportToDatabase;

/**
 * The optional database delivery channel. It writes the report to the
 * `visual_feedback_reports` table — but only when that (opt-in, non-auto-loaded) migration has
 * been published and run: without the table it is simply unavailable, never an error. It records
 * a pending receipt and enqueues its own SendReportToDatabase job (tuned per `channels.database`),
 * which does the UPSERT keyed on the report UUID off the submit path and settles through the
 * ReportDeliveryTracker.
 *
 * It implements RetainsReport: an admin opens a stored report and expects its screenshot to
 * still be there, so the storage-lifecycle refcount excludes this channel and
 * never auto-deletes its attachments — retention and prune own them.
 */
final readonly class DatabaseChannel implements ReportChannel, RetainsReport
{
    public function __construct(
        private Config $config,
        private Bus $bus,
        private ReceiptStore $receipts,
    ) {}

    public function key(): string
    {
        return 'database';
    }

    /** Available only when the opt-in table exists — a missing table is a defined skip, not a crash. */
    public function isAvailable(): bool
    {
        return Schema::hasTable($this->table());
    }

    public function dispatch(Report $report): void
    {
        $this->receipts->record($report->id, $this->key(), DeliveryStatus::Pending);

        $job = new SendReportToDatabase(
            report: $report,
            tries: $this->tries(),
            backoffSeconds: $this->backoff(),
        );

        $queue = $this->config->get('visual-feedback.channels.database.queue');

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        $this->bus->dispatch($job);
    }

    private function table(): string
    {
        $table = $this->config->get('visual-feedback.database.table');

        return is_string($table) && $table !== '' ? $table : 'visual_feedback_reports';
    }

    private function tries(): int
    {
        $tries = $this->config->get('visual-feedback.channels.database.tries');

        return is_numeric($tries) && (int) $tries > 0 ? (int) $tries : 3;
    }

    private function backoff(): int
    {
        $backoff = $this->config->get('visual-feedback.channels.database.backoff');

        return is_numeric($backoff) && (int) $backoff >= 0 ? (int) $backoff : 30;
    }
}
