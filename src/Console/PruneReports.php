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
use Pushery\VisualFeedback\Support\EnvFlag;

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

        // A window under one day is no retention: zero or a negative number puts the cutoff at now
        // or after it, and every report would go. Refused rather than read as "keep forever", so a
        // scheduler that runs this command reports the value instead of quietly keeping everything.
        if ((int) $days < 1) {
            $this->error((string) __('visual-feedback::messages.console.prune.below_one_day', ['days' => (string) $days]));

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays((int) $days);
        $deliveredOnly = EnvFlag::boolean($config->get('visual-feedback.retention.prune_delivered_only'), true);
        $disk = $filesystem->disk($this->attachmentsDisk($config));
        $root = $this->attachmentsDirectory($config);
        $pruned = 0;
        $kept = 0;

        $db->connection()->table($table)
            ->where('created_at', '<', $cutoff)
            ->chunkById(200, function (iterable $rows) use (&$pruned, &$kept, $disk, $deliveredOnly, $db, $table, $pruner, $root): void {
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

                    // A failed delete answers `false` on a disk that is not configured to throw.
                    // The row is the only reference to those files, so it stays for the next run.
                    if ($paths !== []) {
                        if (! $disk->delete($paths)) {
                            $kept++;

                            continue;
                        }

                        $pruner->prune($disk, $paths, $root);
                    }

                    $db->connection()->table($table)->where('id', $row->id)->delete();
                    $pruned++;
                }
            });

        $this->info(trans_choice('visual-feedback::messages.console.prune.pruned', $pruned, ['count' => $pruned]));

        if ($kept > 0) {
            $this->error(trans_choice('visual-feedback::messages.console.prune.kept', $kept, ['count' => $kept]));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Whether a row's `deliveries` JSON map still has any OTHER channel at `pending`.
     *
     * The row's own channel is excluded. `deliveries` is a snapshot the database job takes while
     * it writes the row, and the job settles its own receipt only after the write, so a row whose
     * later refresh did not land still carries `database: pending` for itself. With
     * `prune_delivered_only` on (the shipped default) counting it would hold back every such row,
     * and the retention window would silently do nothing.
     *
     * The delivery tracker rewrites the column on every terminal settle, so a sibling channel
     * that settles after the snapshot reaches the row too, and a report is held back only while a
     * delivery is really pending.
     *
     * Excluding it loses no information, because the row itself is the receipt: a row exists
     * only where the database job's upsert succeeded. Reading
     * the delivery truth out of the ReceiptStore instead would NOT work — its TTL is
     * `reports_days`, the same span as the prune cutoff, so the receipt has expired by the
     * moment a row becomes prunable and the guard would flip from "never prunes" to "always
     * prunes".
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
