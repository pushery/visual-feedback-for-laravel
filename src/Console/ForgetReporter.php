<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\ConnectionResolverInterface as ConnectionResolver;
use Illuminate\Database\Query\Builder;
use Pushery\VisualFeedback\Attachments\EmptyDirectoryPruner;
use Pushery\VisualFeedback\Channels\Webhook\WebhooksPlatform;
use Pushery\VisualFeedback\Console\Concerns\ResolvesReportStorage;
use Pushery\VisualFeedback\Privacy\ErasedReporters;
use Pushery\VisualFeedback\Support\TableLookup;

/**
 * The DSAR erasure: delete every stored report of a reporter and its attachment files.
 *
 * A reporter is named one of two ways. By address, `forget <email>`, which erases every report
 * stored under it, signed in or not, or with `--guests-only` only the ones sent as a guest. Or by
 * the id of a signed-in reporter, `forget --reporter=<id>`, which erases that account's reports
 * under whatever address it used, and with `--guest-email=<email>` also the guest reports under
 * that address. Addresses move between accounts: erasing by address alone takes another account's
 * signed-in reports under a reused address and leaves this account's under an old one, which is
 * what the id is for. Chunked, files-before-row like the prune, so a large history streams
 * and never orphans a file: a report whose files the disk would not delete keeps its row, is not
 * counted as erased, and turns the exit code into a failure. A default install without the opt-in
 * table is a clean no-op.
 *
 * It also records the address as erased (ErasedReporters), so a delivery job still waiting in
 * the queue, backing off, or retried later does not store, mail or post the report again.
 *
 * The command is honest about its reach, and prints a note for every copy it cannot erase: a
 * mail already delivered sits in the inbox, a delivery that failed for good keeps an encrypted
 * copy in the queue's failed jobs, and with the webhook platform installed its delivery log keeps
 * the payload for the platform's own retention. Erasing those is the host's responsibility.
 *
 * Whether an address matches regardless of case is the database's decision, and nothing else
 * is. Nothing normalizes the address — not the widget, not the guard resolver that reads it off
 * the host's user model, and not the write path — and the query compares with the column's
 * collation, which keeps it on the `reporter_email` index. MySQL's default `utf8mb4` collations
 * ignore case, PostgreSQL and SQLite do not: a request in another case erases on MySQL and
 * reports "no reports found" on the other two. An operator who gets that message should re-run
 * with the spelling as it was submitted. This is stated in the argument description too, because
 * that is what an operator reads at the moment the command finds nothing.
 *
 * The same MySQL collations also ignore accents, character width and letters spelled out, so the
 * query alone takes `josé@example.com` for `jose@example.com` and `straße@example.com` for
 * `strasse@example.com`: other people's mailboxes. A row the query returns is erased only when
 * its address equals the requested one up to case (sameAddress()), so on every engine the answer
 * is negative rather than falsely positive, and no report of another address is erased.
 *
 * Folding case on every engine belongs at the write path, both sides at once, never in a
 * `lower()` wrapper in this query: that drops the index, and SQL `lower()` and PHP
 * `mb_strtolower()` do not agree on non-ASCII local parts.
 */
final class ForgetReporter extends Command
{
    use ResolvesReportStorage;

    protected $signature = 'visual-feedback:forget
        {email? : The reporter email address to erase, matched exactly except that MySQL ignores its case, so use the spelling the report was submitted with}
        {--guests-only : With an email, erase only the reports sent as a guest under it, and keep the signed-in reports of any account that used the same address}
        {--reporter= : Instead of an email, erase every report of the signed-in reporter with this id, under any address it used}
        {--guest-email= : With --reporter, also erase the reports sent as a guest under this address}';

    protected $description = 'Erase all stored reports of a reporter, by email or by the id of a signed-in reporter, and their attachment files — a DSAR erasure.';

    public function handle(Config $config, ConnectionResolver $db, FilesystemFactory $filesystem, EmptyDirectoryPruner $pruner, ErasedReporters $erased, WebhooksPlatform $platform): int
    {
        $email = $this->given($this->argument('email'));
        $reporter = $this->given($this->option('reporter'));
        $guestEmail = $this->given($this->option('guest-email'));
        $guestsOnly = $this->option('guests-only') === true;

        $refusal = match (true) {
            $email !== null && $reporter !== null => (string) __('visual-feedback::messages.console.forget.two_subjects'),
            $guestEmail !== null && $reporter === null => (string) __('visual-feedback::messages.console.forget.guest_email_needs_reporter'),
            $guestsOnly && $email === null => (string) __('visual-feedback::messages.console.forget.guests_only_needs_email'),
            default => null,
        };

        if ($refusal !== null) {
            $this->error($refusal);

            return self::INVALID;
        }

        // Each erasure is a scope on the reports table, the two lines that report on it, and the
        // address its rows must carry when it names one (sameAddress()). The markers go first,
        // whether or not the table or a row exists: a delivery still in the queue for one of these
        // reports reads its marker and stores, mails or posts nothing.
        $erasures = [];

        if ($reporter !== null) {
            $erased->rememberReporter($reporter);
            $erasures[] = [static fn (Builder $query): Builder => $query->where('reporter_id', $reporter), 'visual-feedback::messages.console.forget.erased_reporter', 'visual-feedback::messages.console.forget.kept_reporter', ['id' => $reporter], null];

            if ($guestEmail !== null) {
                $erased->rememberGuest($guestEmail);
                $erasures[] = [static fn (Builder $query): Builder => $query->where('reporter_email', $guestEmail)->where('is_guest', true), 'visual-feedback::messages.console.forget.erased_guest', 'visual-feedback::messages.console.forget.kept_guest', ['email' => $guestEmail], $guestEmail];
            }
        } elseif ($email !== null && $guestsOnly) {
            $erased->rememberGuest($email);
            $erasures[] = [static fn (Builder $query): Builder => $query->where('reporter_email', $email)->where('is_guest', true), 'visual-feedback::messages.console.forget.erased_guest', 'visual-feedback::messages.console.forget.kept_guest', ['email' => $email], $email];
        } elseif ($email !== null) {
            $erased->remember($email);
            $erasures[] = [static fn (Builder $query): Builder => $query->where('reporter_email', $email), 'visual-feedback::messages.console.forget.erased', 'visual-feedback::messages.console.forget.kept', ['email' => $email], $email];
        } else {
            $this->error((string) __('visual-feedback::messages.console.forget.no_subject'));

            return self::INVALID;
        }

        $table = $this->reportsTable($config);

        if (! TableLookup::exists($table)) {
            $this->info((string) __('visual-feedback::messages.console.forget.no_table'));
            $this->warnAboutCopiesOutOfReach($platform);

            return self::SUCCESS;
        }

        $disk = $filesystem->disk($this->attachmentsDisk($config));
        $root = $this->attachmentsDirectory($config);
        $failed = false;

        foreach ($erasures as [$scope, $erasedLine, $keptLine, $subject, $address]) {
            $forgotten = 0;
            $kept = 0;

            $scope($db->connection()->table($table))
                ->chunkById(200, function (iterable $rows) use (&$forgotten, &$kept, $disk, $db, $table, $pruner, $root, $address): void {
                    foreach ($rows as $row) {
                        // Another address the column's collation takes for this one is another
                        // person's: not erased, and not counted either way.
                        if ($address !== null && ! $this->sameAddress($row->reporter_email, $address)) {
                            continue;
                        }

                        // Files first, then the row — a crash between the two never leaves an
                        // orphaned file. And the emptied per-report directory goes with them: each
                        // upload lives in its own random subdirectory, so deleting the files alone
                        // left one empty directory per pruned report, forever. The orphan sweep
                        // would eventually walk them away, but that is a separate command a consumer
                        // has to schedule, and retention is supposed to leave nothing behind — the
                        // pruner exists for exactly this and both other callers already use it.
                        $paths = $this->attachmentPaths($row->attachments);

                        // A disk that is not configured to throw answers a failed delete with `false`.
                        // The row is the only reference to those files, so it stays for the next run,
                        // and the report is not counted as erased.
                        if ($paths !== []) {
                            if (! $disk->delete($paths)) {
                                $kept++;

                                continue;
                            }

                            $pruner->prune($disk, $paths, $root);
                        }

                        $db->connection()->table($table)->where('id', $row->id)->delete();
                        $forgotten++;
                    }
                });

            $this->info(trans_choice($erasedLine, $forgotten, ['count' => $forgotten, ...$subject]));

            if ($kept > 0) {
                $this->error(trans_choice($keptLine, $kept, ['count' => $kept, ...$subject]));
                $failed = true;
            }
        }

        $this->warnAboutCopiesOutOfReach($platform);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** An argument or option value that names something: a non-blank string, as given. */
    private function given(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Whether a stored address is the requested one, regardless of case and of nothing else.
     *
     * The query has already matched with the column's collation. Where that collation ignores
     * case, a row in another case is the same address; where it also ignores accents, width or
     * letters spelled out, as MySQL's default collations do, the row belongs to another address.
     */
    private function sameAddress(mixed $stored, string $requested): bool
    {
        return is_string($stored) && mb_strtolower($stored) === mb_strtolower($requested);
    }

    /**
     * Name every copy of a report this command cannot erase.
     *
     * A mailed copy sits in the mail provider. A delivery job that failed for good stays in the
     * queue's failed jobs until somebody removes it, encrypted because the jobs implement
     * ShouldBeEncrypted. And with the webhook platform installed, its delivery log keeps each
     * payload for the platform's own retention.
     */
    private function warnAboutCopiesOutOfReach(WebhooksPlatform $platform): void
    {
        $this->warn((string) __('visual-feedback::messages.console.forget.mail_note'));
        $this->warn((string) __('visual-feedback::messages.console.forget.queue_note'));

        // Not tied to the webhook channel being on today: a channel switched off last month left
        // its payloads in the platform's log all the same.
        if ($platform->isInstalled()) {
            $this->warn((string) __('visual-feedback::messages.console.forget.platform_note'));
        }
    }
}
