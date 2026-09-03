<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Channels\Mail\ReportMail;
use Pushery\VisualFeedback\Channels\ReportDeliveryTracker;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Support\CategoryLabels;
use Throwable;

/**
 * The mail channel's own queued job. It carries the plain, queue-safe pieces
 * the mailable needs and sends synchronously inside the worker — so the channel gets one
 * uniform lifecycle shape with the others: handle() delivers then settles DELIVERED, and
 * failed() (after the retries in `channels.mail` are exhausted) settles FAILED. The receipt,
 * the ReportDelivered/ReportDeliveryFailed event and the attachment-refcount step all flow
 * through the single ReportDeliveryTracker — never from a second path.
 */
final class SendReportMail implements ShouldQueue
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

    public function handle(Mailer $mailer, ReportDeliveryTracker $tracker, CategoryLabels $labels, LoggerInterface $logger): void
    {
        $this->warnIfFromEqualsTo($logger);

        // ReportMail resolves the category label from $labels inside envelope()/content(),
        // which Laravel runs under the render locale set below — so it is localized correctly.
        $mailable = new ReportMail($this->report, $this->mail, $labels);

        // The render locale is resolved once, on the request, and carried — never the worker's.
        if ($this->locale !== null) {
            $mailable->locale($this->locale);
        }

        $mailer->send($mailable);

        $tracker->settleDelivered($this->report, 'mail');
    }

    /** Retries exhausted → the ONE terminal failure path for this channel. */
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
