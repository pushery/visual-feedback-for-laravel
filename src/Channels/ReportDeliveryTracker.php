<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\ConnectionResolverInterface as ConnectionResolver;
use Pushery\VisualFeedback\Attachments\AttachmentPolicy;
use Pushery\VisualFeedback\Attachments\EmptyDirectoryPruner;
use Pushery\VisualFeedback\Contracts\ReportChannel;
use Pushery\VisualFeedback\Contracts\RetainsReport;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Events\ReportDelivered;
use Pushery\VisualFeedback\Events\ReportDeliveryFailed;
use Pushery\VisualFeedback\Support\RedactedFailure;
use Throwable;

/**
 * The single source of delivery-lifecycle truth. Every channel
 * settles EXACTLY ONCE here — terminally, never per retry — and that is now ENFORCED rather than
 * assumed: the TerminalSettleGate claims each (report, channel) pair, and a second settle from
 * any caller returns without doing anything. It had to become structural because a caller that
 * duplicates is not hypothetical — under the sync queue the framework settles the job through
 * failed() and then rethrows into ChannelRegistry's catch, which settles it again. The class does
 * the three
 * things that must happen together and nowhere else: write the delivery receipt, fire the
 * lifecycle event (strings only, queue-safe), and drive the attachment refcount. Centralizing
 * it is the fix for a pair of defects that scattered cleanup invites — a dead success hook that
 * never cleaned up, leaking files indefinitely, and a double-decrement that deleted them early: here there is one path per
 * channel, and the refcount is only ever armed for the all-transient case.
 *
 * A settle runs inside the job of a channel that has already delivered or terminally failed, so
 * an exception after the claim is handled by what it would cost. When the receipt itself cannot
 * be written, nothing else has happened yet: the claim is handed back and the exception rethrown,
 * so the job's retry settles in full. A listener that throws, or a cleanup the disk refuses, is
 * reported and the settle carries on: failing the job there would send the mail or post the
 * webhook a second time, and the retry would find the pair claimed and settle nothing.
 *
 * begin() decides the cleanup policy from the channel set: a retaining channel (database)
 * means the files are kept for later viewing, so nothing is armed and nothing is auto-deleted
 * (retention/prune owns them); zero channels means nothing will ever need the files, so they
 * are deleted at once; otherwise the refcount is armed with the transient-channel count.
 */
final readonly class ReportDeliveryTracker
{
    public function __construct(
        private ReceiptStore $receipts,
        private Events $events,
        private AttachmentRefcount $refcount,
        private FilesystemFactory $filesystem,
        private Config $config,
        private EmptyDirectoryPruner $pruner,
        private AttachmentPolicy $policy,
        private TerminalSettleGate $gate,
        private ConnectionResolver $db,
    ) {}

    /**
     * Arm the lifecycle for a report about to be dispatched to its channels.
     *
     * @param  list<ReportChannel>  $channels  the enabled + available channels
     */
    public function begin(Report $report, array $channels): void
    {
        $transientCount = count(array_filter(
            $channels,
            static fn (ReportChannel $channel): bool => ! $channel instanceof RetainsReport,
        ));

        // A retaining channel keeps the report + its files beyond delivery — never auto-delete.
        if ($transientCount !== count($channels)) {
            return;
        }

        // No channel at all will ever need the files → delete them immediately.
        if ($transientCount === 0) {
            $this->discardAttachments($report);

            return;
        }

        $this->refcount->arm($report->id, $transientCount);
    }

    /** Record a terminal SUCCESS for one channel: receipt + event + refcount step. */
    public function settleDelivered(Report $report, string $channel): void
    {
        if (! $this->gate->claim($report->id, $channel)) {
            return;
        }

        $this->recordOrHandBack($report->id, $channel, DeliveryStatus::Delivered);
        $this->refreshStoredDeliveries($report);
        $this->notify(new ReportDelivered($channel, $report->id));
        $this->afterTerminal($report);
    }

    /** Record a terminal FAILURE for one channel: receipt + event (strings) + refcount step. */
    public function settleFailed(Report $report, string $channel, Throwable $exception): void
    {
        if (! $this->gate->claim($report->id, $channel)) {
            return;
        }

        $this->recordOrHandBack($report->id, $channel, DeliveryStatus::Failed);
        $this->refreshStoredDeliveries($report);
        // A listener forwards this to a chat channel or a ticket, so it gets the original class
        // and a message with the statement, the response body and any URL secret taken out.
        $this->notify(new ReportDeliveryFailed(
            $channel,
            $report->id,
            RedactedFailure::classOf($exception),
            RedactedFailure::message($exception),
        ));
        $this->afterTerminal($report);
    }

    /**
     * Bring the stored row's `deliveries` up to the receipts, for a report the database channel took.
     *
     * The database job writes that column once, as it stores the row, so a channel that settles
     * after it stayed `pending` there for good, and the retention prune keeps a report with a
     * pending delivery. Rewriting the column on every terminal settle closes that from both
     * sides: a settle before the row exists finds no row, and the job's own snapshot then reads
     * the settled receipt; a settle after it updates the row.
     *
     * Best effort by design. This runs inside the job of whichever channel just settled, after
     * its delivery, and an exception here would fail that job into a retry that delivers twice.
     */
    private function refreshStoredDeliveries(Report $report): void
    {
        try {
            $receipts = $this->receipts->receipts($report->id);

            if (! array_key_exists(DatabaseChannel::KEY, $receipts)) {
                return;
            }

            $this->db->connection()->table($this->reportsTable())
                ->where('uuid', $report->id)
                ->update(['deliveries' => json_encode(
                    array_map(static fn (DeliveryStatus $status): string => $status->value, $receipts),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                )]);
        } catch (Throwable $exception) {
            report(RedactedFailure::standIn($exception));
        }
    }

    private function reportsTable(): string
    {
        $table = $this->config->get('visual-feedback.database.table');

        return is_string($table) && $table !== '' ? $table : 'visual_feedback_reports';
    }

    /**
     * Write the receipt, or hand the claim back and rethrow.
     *
     * Nothing else of the settle has run at that point, so the job's retry finds the pair
     * unclaimed and settles it in full. A receipt left at `pending` would also keep the report
     * from the retention prune.
     */
    private function recordOrHandBack(string $reportId, string $channel, DeliveryStatus $status): void
    {
        try {
            $this->receipts->record($reportId, $channel, $status);
        } catch (Throwable $exception) {
            $this->gate->release($reportId, $channel);

            throw RedactedFailure::standIn($exception);
        }
    }

    /** Fire a lifecycle event, and report a listener that throws instead of failing the settle. */
    private function notify(ReportDelivered|ReportDeliveryFailed $event): void
    {
        try {
            $this->events->dispatch($event);
        } catch (Throwable $exception) {
            report(RedactedFailure::standIn($exception));
        }
    }

    private function afterTerminal(Report $report): void
    {
        // The receipt is written and the event is out, so handing the claim back here would fire
        // it a second time. A file left behind by a failure is the orphan sweep's to collect.
        try {
            // Not armed → a retaining holder is present (files kept) or the counter is already
            // released; either way this terminal event drives no cleanup.
            if (! $this->refcount->isArmed($report->id)) {
                return;
            }

            if ($this->refcount->decrement($report->id) <= 0) {
                $this->refcount->release($report->id);
                $this->discardAttachments($report);
            }
        } catch (Throwable $exception) {
            report(RedactedFailure::standIn($exception));
        }
    }

    /**
     * Delete a report's files and the per-report directory they leave behind.
     *
     * The refcount calls this once the last transient channel settles, and dispatch calls it
     * straight away for a report no channel took. The database job calls it
     * when it withholds a report whose reporter was erased: the refcount never arms while a
     * channel that retains the report is among its channels, so nothing else would delete them.
     */
    public function discardAttachments(Report $report): void
    {
        if ($report->attachments === []) {
            return;
        }

        $disk = $this->filesystem->disk($this->disk());
        $disk->delete($report->attachments);
        // delete() takes the files and leaves their per-file subdirectory standing, one per
        // report, forever.
        $this->pruner->prune($disk, $report->attachments, $this->policy->directory());
    }

    private function disk(): string
    {
        return $this->policy->disk();
    }
}
