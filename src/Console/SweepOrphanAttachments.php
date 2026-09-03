<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\ConnectionResolverInterface as ConnectionResolver;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Pushery\VisualFeedback\Attachments\EmptyDirectoryPruner;
use Pushery\VisualFeedback\Console\Concerns\ResolvesReportStorage;

/**
 * The age-based orphan sweep — the backstop for a lost cleanup refcount: if the cache-backed refcount is evicted, a transient report's attachments are
 * never deleted, and this sweep catches them once they are old enough.
 *
 * AGE is the ONLY deletion criterion, and the sweep is confined to the package's own attachments
 * directory on the configured disk. A DB match is a KEEP-ALIVE only, never the deletion rule: a
 * DB-diff would be catastrophic with the database channel OFF (no table → EVERY file looks
 * orphaned → it would delete the lot). The min-age MUST exceed the queue retry horizon
 * (latency + tries × backoff) so a file a retrying mail job still needs is never swept — the
 * config default (24 h) clears any realistic backoff.
 */
final class SweepOrphanAttachments extends Command
{
    use ResolvesReportStorage;

    /** How many deleted paths are held before their emptied directories are pruned. */
    private const int PRUNE_BATCH = 500;

    protected $signature = 'visual-feedback:sweep-orphans';

    protected $description = 'Delete orphaned attachment files older than the cutoff that no stored report references.';

    public function handle(Config $config, FilesystemFactory $filesystem, ConnectionResolver $db, EmptyDirectoryPruner $pruner): int
    {
        $disk = $filesystem->disk($this->attachmentsDisk($config));
        $directory = $this->attachmentsDirectory($config);

        $minAge = $config->get('visual-feedback.retention.orphan_attachments_min_age');
        $minAgeMinutes = is_numeric($minAge) && (int) $minAge > 0 ? (int) $minAge : 1440;
        $cutoff = Carbon::now()->subMinutes($minAgeMinutes)->getTimestamp();

        $referenced = $this->referencedPaths($config, $db);
        $swept = 0;
        /** @var list<string> $batch */
        $batch = [];

        foreach ($this->candidates($disk, $directory) as $path => $lastModified) {
            if ($lastModified >= $cutoff) {
                continue; // too fresh — a running retry may still need it
            }

            if (isset($referenced[$path])) {
                continue; // a stored report still references it — retention/prune owns that file
            }

            $disk->delete($path);
            $batch[] = $path;
            $swept++;

            // The files are gone; their per-file subdirectories are not, and this command is the
            // one that would otherwise have to walk them on every future run. Pruning PER BATCH
            // rather than once at the end is what keeps this bounded: the old single call needed
            // the full list of every path the run had deleted, held until the loop finished.
            if (count($batch) >= self::PRUNE_BATCH) {
                $pruner->prune($disk, $batch, $directory);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $pruner->prune($disk, $batch, $directory);
        }

        $this->info(trans_choice('visual-feedback::messages.console.sweep.swept', $swept, ['count' => $swept, 'minutes' => $minAgeMinutes]));

        return self::SUCCESS;
    }

    /**
     * The files under the attachments directory, with the modification time each one was listed
     * with — streamed, one at a time.
     *
     * `allFiles()` cannot be used for this, and the reason is not obvious from its name:
     * `FilesystemAdapter::files()` appends `->sortByPath()`, which calls `toArray()` on the
     * listing, so the whole directory is materialized as StorageAttributes objects before the
     * first path is returned. That was the single largest allocation this command made, several
     * times bigger than the reference map the chunked query builds. Iterating the driver's
     * listing directly keeps it a generator.
     *
     * The bigger win is on remote storage, and it is a latency one rather than a memory one. The
     * listing already carries each file's modification time — S3 returns it inside the
     * ListObjectsV2 response — while `$disk->lastModified($path)` is passed straight through to
     * the adapter and costs one HeadObject request PER FILE. Reading it off the listing turns a
     * sweep of 50 000 remote files from 50 000 sequential round trips into the listing itself.
     *
     * A Filesystem implementation that is not the framework's adapter has no driver to ask, so
     * that case keeps the eager walk: correct everywhere, fast where it can be.
     *
     * @return iterable<string, int> path => last-modified unix timestamp
     */
    private function candidates(Filesystem $disk, string $directory): iterable
    {
        if (! $disk instanceof FilesystemAdapter) {
            foreach ($disk->allFiles($directory) as $path) {
                yield $path => $disk->lastModified($path);
            }

            return;
        }

        foreach ($disk->getDriver()->listContents($directory, true) as $item) {
            if (! $item->isFile()) {
                continue;
            }

            // An adapter may list without a timestamp; ask for that one file rather than
            // treating a missing time as "ancient" and deleting a file that may be brand new.
            yield $item->path() => $item->lastModified() ?? $disk->lastModified($item->path());
        }
    }

    /**
     * The set of attachment paths any stored report references — a KEEP-ALIVE, empty without the
     * opt-in table (then age alone decides, which is correct: no table means no persistent store).
     *
     * @return array<string, true>
     */
    private function referencedPaths(Config $config, ConnectionResolver $db): array
    {
        $table = $this->reportsTable($config);

        if (! Schema::hasTable($table)) {
            return [];
        }

        $referenced = [];

        $db->connection()->table($table)
            ->select(['id', 'attachments'])
            ->chunkById(500, function (iterable $rows) use (&$referenced): void {
                foreach ($rows as $row) {
                    foreach ($this->attachmentPaths($row->attachments) as $path) {
                        $referenced[$path] = true;
                    }
                }
            });

        return $referenced;
    }
}
