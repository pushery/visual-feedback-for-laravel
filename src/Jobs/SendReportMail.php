<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Channels\Mail\ReportMail;
use Pushery\VisualFeedback\Channels\ReportDeliveryTracker;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Privacy\ErasedReporters;
use Pushery\VisualFeedback\Privacy\ReporterWasErased;
use Pushery\VisualFeedback\Support\CategoryLabels;
use Pushery\VisualFeedback\Support\RedactedFailure;
use Throwable;

/**
 * The mail channel's own queued job. It carries the plain, queue-safe pieces
 * the mailable needs and sends synchronously inside the worker — so the channel gets one
 * uniform lifecycle shape with the others: handle() delivers then settles delivered, handing a
 * settle the cache refuses to SettleDelivery, and
 * failed() (after the retries in `channels.mail` are exhausted) settles failed. The receipt,
 * the ReportDelivered/ReportDeliveryFailed event and the attachment-refcount step all flow
 * through the single ReportDeliveryTracker — never from a second path.
 *
 * Queued encrypted: the report carries the reporter's name, address and message, and a job that
 * fails for good stays in the queue's failed jobs until somebody removes it. A report whose
 * reporter was erased after submitting it is withheld (ErasedReporters).
 */
final class SendReportMail implements ShouldBeEncrypted, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{to: ?string, from: array{address: ?string, name: ?string}, reply_to_reporter: bool, attach_files: bool, disk: ?string}  $mail
     */
    public function __construct(
        public readonly Report $report,
        public readonly array $mail,
        public readonly ?string $locale,
        public readonly int $tries,
        private readonly int $backoffSeconds,
    ) {}

    public function handle(Mailer $mailer, ReportDeliveryTracker $tracker, CategoryLabels $labels, LoggerInterface $logger, ErasedReporters $erased): void
    {
        // The reporter was erased after this report was submitted. Mailing it now would bring
        // back a copy the erasure was meant to end, so the channel ends here, recorded as not
        // delivered.
        if ($erased->covers($this->report)) {
            $tracker->settleFailed($this->report, 'mail', new ReporterWasErased);

            return;
        }

        $this->warnIfFromEqualsTo($logger);

        // ReportMail resolves the category label from $labels inside envelope()/content(),
        // which Laravel runs under the render locale set below — so it is localized correctly.
        $mailable = new ReportMail($this->report, $this->mail, $labels);

        // The render locale is resolved once, on the request, and carried — never the worker's.
        if ($this->locale !== null) {
            $mailable->locale($this->locale);
        }

        // What a job throws, the worker reports and the failed jobs keep, so a transport error that
        // names a URL or a DSN goes out as a stand-in without its secrets.
        try {
            $mailer->send($mailable);
        } catch (Throwable $exception) {
            throw RedactedFailure::standIn($exception);
        }

        // The mail is out. A settle the cache refuses now goes to a job of its own, because a
        // retry of this one would send the mail a second time.
        SettleDelivery::afterDelivery($tracker, $this->report, 'mail', $this->connection, $this->queue, $this->tries, $this->backoffSeconds);
    }

    /** Retries exhausted → the one terminal failure path for this channel. */
    public function failed(Throwable $exception): void
    {
        Container::getInstance()->make(ReportDeliveryTracker::class)
            ->settleFailed($this->report, 'mail', $exception);
    }

    public function backoff(): int
    {
        return $this->backoffSeconds;
    }

    /**
     * A from == to address blocks many SMTP servers as a mail loop. It is a
     * configuration mistake, not a hard error — so it is logged as a warning and the send still
     * proceeds; the docs recommend a dedicated From address.
     */
    private function warnIfFromEqualsTo(LoggerInterface $logger): void
    {
        $from = $this->mail['from']['address'];

        if ($from !== null && $from === $this->mail['to']) {
            $logger->warning(
                'visual-feedback: mail.from equals mail.to — many SMTP servers reject a self-addressed mail as a loop. Configure a dedicated From address.',
                ['address' => $from],
            );
        }
    }
}
