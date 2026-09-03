<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Support\CategoryLabels;

/**
 * The admin report mail — hard-won lessons encoded as behavior, not lore:
 *
 *  - `Mail::to()` does NOT survive queue serialization, so the recipient
 *    lives in the ENVELOPE, which does.
 *  - The envelope is built STRICTLY from config — no in-code fallback address (the drift
 *    being a hardcoded noreply@ that silently shadows the configured one).
 *  - The subject is CRLF-stripped, so a report subject can never inject a mail header.
 *  - Reply-To is the reporter (when `reply_to_reporter`), so a reply reaches the person.
 *  - The category label is resolved from `CategoryLabels` INSIDE envelope()/content(), which
 *    Laravel runs under the mail's render locale (->locale()) — so it is localized in `mail.locale`,
 *    never the worker's random locale (the label was previously resolved in the wrong locale).
 *
 * This is a PLAIN mailable — the queuing is owned by the channel's SendReportMail job, which sends it synchronously inside the worker and then settles the delivery.
 * User content (message, subject, context values, metadata values) renders INERT: fenced code
 * for the free text, MailCell-escaped cells for the table/list — no user Markdown/HTML/auto-links.
 */
final class ReportMail extends Mailable
{
    /**
     * @param  array{to: ?string, from: array{address: ?string, name: ?string}, reply_to_reporter: bool, attach_files?: bool, disk?: ?string}  $mail
     */
    public function __construct(
        public readonly Report $report,
        public readonly array $mail,
        private readonly CategoryLabels $labels,
    ) {}

    public function envelope(): Envelope
    {
        $reporterEmail = $this->mail['reply_to_reporter'] ? $this->report->reporter->email : null;

        return new Envelope(
            from: new Address((string) $this->mail['from']['address'], $this->mail['from']['name']),
            to: [new Address((string) $this->mail['to'])],
            replyTo: is_string($reporterEmail) && $reporterEmail !== '' ? [new Address($reporterEmail)] : [],
            subject: $this->subjectLine(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'visual-feedback::mail.report',
            with: [
                'report' => $this->report,
                'categoryLabel' => $this->categoryLabel(),
            ],
        );
    }

    /**
     * The report's stored files as mail attachments — ONLY in the `attach_files` mode (default).
     * A host with large reports sets `mail.attach_files` false to keep the mail small: the body
     * still lists how many files there are, but nothing is attached. Each file is pulled from the
     * configured (private) disk and named by its sanitized basename via ->as().
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if (($this->mail['attach_files'] ?? true) !== true) {
            return [];
        }

        $disk = is_string($this->mail['disk'] ?? null) ? $this->mail['disk'] : null;

        return array_map(
            static fn (string $path): Attachment => Attachment::fromStorageDisk($disk, $path)->as(basename($path)),
            $this->report->attachments,
        );
    }

    /** The category label, resolved under the mail's render locale (see the class docblock). */
    private function categoryLabel(): string
    {
        return $this->labels->label($this->report->category);
    }

    /** The subject line — CRLF-stripped so a report subject can never inject a mail header. */
    private function subjectLine(): string
    {
        $subject = $this->report->subject ?? $this->categoryLabel();
        $clean = (string) preg_replace('/[\r\n]+/', ' ', $subject);

        return trim($clean) !== '' ? $clean : $this->categoryLabel();
    }
}
