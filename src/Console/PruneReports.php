<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\ConnectionResolverInterface as ConnectionResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Pushery\VisualFeedback\Attachments\EmptyDirectoryPruner;
use Pushery\VisualFeedback\Console\Concerns\ResolvesReportStorage;

/**
 * Deletes reports past the retention cutoff — and their attachment files. This is
 * the ONLY documented retention entry point: `model:prune` scans only app/Models (a package
 * Prunable is invisible → a No-op retention), and MassPrunable deletes without resolving each
 * row's attachment paths → the files would leak forever through the retention itself. So this
 * command deletes each row's FILES FIRST, then the row, chunked by id so a huge table streams.
 *
 * `retention.reports_days` is the cutoff (null = keep forever). `retention.prune_delivered_only`
 * keeps a report whose delivery has not landed (any OTHER channel still `pending` — the row's own
 * writer is excluded, see hasPendingDelivery). Without the opt-in table it is a clean no-op — a
 * default install has no table to prune.
 */
final class PruneReports extends Command
{
    use ResolvesReportStorage;

    /**
     * The channel key of the writer of this table — DatabaseChannel::key(). Its own receipt in a
     * row's `deliveries` snapshot is never anything but `pending`, so the delivered-only guard
     * has to skip it. `PruneIgnoresItsOwnChannelReceiptTest` holds the string against the channel.
     */
    private const string OWN_CHANNEL = 'database';

    protected $signature = 'visual-feedback:prune';

    protected $description = 'Delete visual-feedback reports past the retention cutoff, and their attachment files.';

    public function handle(Config $config, ConnectionResolver $db, FilesystemFactory $filesystem, EmptyDirectoryPruner $pruner): int
    {
        $table = $this->reportsTable($config);

        if (! Schema::hasTable($table)) {
            $this->info((string) __('visual-feedback::messages.console.prune.no_table'));

            return self::SUCCESS;
        }

        $days = $config->get('visual-feedback.retention.reports_days');

        if (! is_numeric($days)) {
            $this->info((string) __('visual-feedback::messages.console.prune.no_retention'));

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subDays((int) $days);
        $deliveredOnly = (bool) $config->get('visual-feedback.retention.prune_delivered_only');
        $disk = $filesystem->disk($this->attachmentsDisk($config));
        $root = $this->attachmentsDirectory($config);
        $pruned = 0;

        $db->connection()->table($table)
            ->where('created_at', '<', $cutoff)
            ->chunkById(200, function (iterable $rows) use (&$pruned, $disk, $deliveredOnly, $db, $table, $pruner, $root): void {
                foreach ($rows as $row) {
                    if ($deliveredOnly && $this->hasPendingDelivery($row->deliveries)) {
                        continue; // keep an undelivered report until it lands
                    }

                    // Files FIRST, then the row — a crash between the two never leaves an
                    // orphaned file. And the emptied per-report directory goes with them: each
                    // upload lives in its own random subdirectory, so deleting the files alone
                    // left one empty directory PER PRUNED REPORT, forever. The orphan sweep
                    // would eventually walk them away, but that is a separate command a consumer
                    // has to schedule, and retention is supposed to leave nothing behind — the
                    // pruner exists for exactly this and both other callers already use it.
                    $paths = $this->attachmentPaths($row->attachments);

                    if ($paths !== []) {
                        $disk->delete($paths);
                        $pruner->prune($disk, $paths, $root);
                    }

                    $db->connection()->table($table)->where('id', $row->id)->delete();
                    $pruned++;
                }
            });

        $this->info(trans_choice('visual-feedback::messages.console.prune.pruned', $pruned, ['count' => $pruned]));

        return self::SUCCESS;
    }

    /**
     * Whether a row's `deliveries` JSON map still has any OTHER channel at `pending`.
     *
     * The row's own channel is excluded, and that exclusion is what makes this guard mean
     * anything at all. `deliveries` is a snapshot the database job takes while it writes the
     * row, and the job settles its own receipt only AFTER the write — so the map it stores
     * always carries `database: pending` for itself, on every row, forever. Nothing else ever
     * writes the column: the upsert refreshes it only on a queue retry, which snapshots the
     * same moment again. With `prune_delivered_only` on (the shipped default) the unfiltered
     * check therefore skipped EVERY row the table can hold, and the command reported "no
     * reports past the retention cutoff" while the retention window silently did nothing.
     *
     * Excluding it loses no information, because the row itself is the receipt: this table has
     * exactly one writer, and a row exists only where that writer's upsert succeeded. Reading
     * the delivery truth out of the ReceiptStore instead would NOT work — its TTL is
     * `reports_days`, the same span as the prune cutoff, so the receipt has expired by the
     * moment a row becomes prunable and the guard would flip from "never prunes" to "always
     * prunes". A sibling channel that had not settled when the snapshot was taken still holds
     * its report back, which is the documented behavior.
     */
    private function hasPendingDelivery(mixed $json): bool
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($decoded)) {
            return false;
        }

        unset($decoded[self::OWN_CHANNEL]);

        return in_array('pending', $decoded, true);
    }
}
