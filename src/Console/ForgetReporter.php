<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\ConnectionResolverInterface as ConnectionResolver;
use Illuminate\Support\Facades\Schema;
use Pushery\VisualFeedback\Attachments\EmptyDirectoryPruner;
use Pushery\VisualFeedback\Console\Concerns\ResolvesReportStorage;

/**
 * The DSAR erasure: delete every stored report of a reporter — matched by email —
 * and its attachment files. Chunked, files-before-row like the prune, so a large history streams
 * and never orphans a file. A default install without the opt-in table is a clean no-op.
 *
 * The command is HONEST about its reach: mail copies already delivered to the admin inbox live
 * OUTSIDE the package's storage, so erasing those is the host's responsibility — printed as a note.
 *
 * THE MATCH IS AN EXACT EQUALITY, AND WHETHER IT IGNORES CASE IS THE DATABASE'S DECISION,
 * NOT THIS PACKAGE'S. Nothing normalizes the address — not the widget, not the guard resolver
 * that reads it off the host's user model, and not the write path — so the column's collation
 * decides: MySQL's default `utf8mb4` family matches case-INSENSITIVELY, PostgreSQL and SQLite
 * do not. The same request erases on one supported engine and reports "no reports found" on
 * another. An operator who gets that message on Postgres should re-run with the spelling as it
 * was submitted; the answer is negative rather than falsely positive, so nothing is ever
 * reported as erased that was not. This is stated in the argument description too, because that
 * is what an operator reads at the moment the command finds nothing.
 *
 * The decision to leave it engine-dependent for 0.1.0 is deliberate, and the two suites that
 * hold it are `tests/Postgres/ForgetEmailMatchIsCaseSensitiveTest.php` and its MySQL twin —
 * they assert OPPOSITE outcomes from the same input, which is the point. Normalizing later
 * belongs at the WRITE path (both sides at once), never as a `lower()` wrapper here: that drops
 * the `reporter_email` index the optional migration now carries, and SQL `lower()` and PHP
 * `mb_strtolower()` do not agree on non-ASCII local parts.
 */
final class ForgetReporter extends Command
{
    use ResolvesReportStorage;

    protected $signature = 'visual-feedback:forget {email : The reporter email address to erase, matched exactly — MySQL ignores case, PostgreSQL and SQLite do not, so use the spelling the report was submitted with}';

    protected $description = 'Erase all stored reports of a reporter (matched on the email exactly) and their attachment files — a DSAR erasure.';

    public function handle(Config $config, ConnectionResolver $db, FilesystemFactory $filesystem, EmptyDirectoryPruner $pruner): int
    {
        $table = $this->reportsTable($config);

        if (! Schema::hasTable($table)) {
            $this->info((string) __('visual-feedback::messages.console.forget.no_table'));

            return self::SUCCESS;
        }

        // Taken straight, with no is_string() guard. The signature declares `email` as a
        // REQUIRED argument, so the framework has already refused the call if it is absent
        // — and every analyzer version agrees it is a string here. Guarding it was the
        // thing that broke: narrowing a value the analyzer already calls string is an
        // error at max level, and this package ships without a committed lock file, so CI
        // resolves a different analyzer than a developer's vendor/ and the verdicts differ.
        $email = $this->argument('email');
        $disk = $filesystem->disk($this->attachmentsDisk($config));
        $root = $this->attachmentsDirectory($config);
        $forgotten = 0;

        $db->connection()->table($table)
            ->where('reporter_email', $email)
            ->chunkById(200, function (iterable $rows) use (&$forgotten, $disk, $db, $table, $pruner, $root): void {
                foreach ($rows as $row) {
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
                    $forgotten++;
                }
            });

        $this->info(trans_choice('visual-feedback::messages.console.forget.erased', $forgotten, ['count' => $forgotten, 'email' => $email]));
        $this->warn((string) __('visual-feedback::messages.console.forget.mail_note'));

        return self::SUCCESS;
    }
}
