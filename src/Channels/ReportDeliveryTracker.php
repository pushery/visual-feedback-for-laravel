<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Pushery\VisualFeedback\Attachments\AttachmentPolicy;
use Pushery\VisualFeedback\Attachments\EmptyDirectoryPruner;
use Pushery\VisualFeedback\Contracts\ReportChannel;
use Pushery\VisualFeedback\Contracts\RetainsReport;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Events\ReportDelivered;
use Pushery\VisualFeedback\Events\ReportDeliveryFailed;
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
            $this->cleanup($report);

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

        $this->receipts->record($report->id, $channel, DeliveryStatus::Delivered);
        $this->events->dispatch(new ReportDelivered($channel, $report->id));
        $this->afterTerminal($report);
    }

    /** Record a terminal FAILURE for one channel: receipt + event (strings) + refcount step. */
    public function settleFailed(Report $report, string $channel, Throwable $exception): void
    {
        if (! $this->gate->claim($report->id, $channel)) {
            return;
        }

        $this->receipts->record($report->id, $channel, DeliveryStatus::Failed);
        $this->events->dispatch(new ReportDeliveryFailed(
            $channel,
            $report->id,
            $exception::class,
            $exception->getMessage(),
        ));
        $this->afterTerminal($report);
    }

    private function afterTerminal(Report $report): void
    {
        // Not armed → a retaining holder is present (files kept) or the counter is already
        // released; either way this terminal event drives no cleanup.
        if (! $this->refcount->isArmed($report->id)) {
            return;
        }

        if ($this->refcount->decrement($report->id) <= 0) {
            $this->refcount->release($report->id);
            $this->cleanup($report);
        }
    }

    private function cleanup(Report $report): void
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

    private function disk(): ?string
    {
        $disk = $this->config->get('visual-feedback.attachments.disk');

        return is_string($disk) && $disk !== '' ? $disk : null;
    }
}
