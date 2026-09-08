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
     * @param  array{to: ?string, from: array{address: ?string, name: ?string}, reply_to_reporter: bool, attach_files?: bool, disk?: ?string, subject_excerpt_length?: int}  $mail
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
        $given = $this->oneLine($this->report->subject ?? '');

        return $given !== '' ? $given : $this->composedSubject();
    }

    /**
     * What a report with no subject of its own gets: `Category - the first words of the message`.
     *
     * The subject field is optional by design, so this is the ordinary case rather than an edge
     * one. It used to be the bare category label, which made an inbox of twenty reports read as
     * "Bug / Bug / Feature / Bug" -- every line identical, none of them saying which to open.
     *
     * The excerpt comes from the MESSAGE and from nothing else. Context and metadata are
     * host-supplied and can carry anything the host decided to attach, up to and including PII;
     * a subject line is the one part of a mail that shows up in notifications and lock screens.
     */
    private function composedSubject(): string
    {
        $excerpt = $this->messageExcerpt();

        return $excerpt === '' ? $this->categoryLabel() : $this->categoryLabel().' - '.$excerpt;
    }

    /**
     * The first words of the message, within the configured budget.
     *
     * Multibyte-safe by construction: `mb_substr` cuts on characters, and a byte-wise cut would
     * split a UTF-8 sequence and put a replacement glyph in the subject line. Where a word
     * boundary sits in the last 40% of the budget the cut moves back to it -- a break mid-word
     * reads like a defect rather than like an excerpt.
     *
     * The ellipsis is appended only when something was actually dropped. On a message that fits,
     * it would claim there is more to read.
     */
    private function messageExcerpt(): string
    {
        $budget = (int) ($this->mail['subject_excerpt_length'] ?? 60);

        if ($budget < 1) {
            return '';
        }

        $message = $this->oneLine($this->report->message);

        if ($message === '' || mb_strlen($message) <= $budget) {
            return $message;
        }

        $cut = mb_substr($message, 0, $budget);
        $space = mb_strrpos($cut, ' ');

        if ($space !== false && $space >= (int) ($budget * 0.6)) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut).'…';
    }

    /**
     * One line, one space between words -- and this is a SECURITY control, not tidiness.
     *
     * A mail header ends at a line break, so a value carrying `\r\n` can append headers of its
     * own. Both halves of the subject line come from the same untrusted place: free text typed by
     * an anonymous reporter, so the excerpt must not re-open the hole the subject already closed.
     *
     * The `[\r\n]` pass is REDUNDANT and stays on purpose. `\s+` already covers both characters,
     * so removing it changes nothing today -- and that is exactly why it is written out: the
     * whitespace collapse is cosmetic in intent and the sort of line somebody narrows to `[ \t]+`
     * while tidying up, at which point the security property leaves with it and no test that
     * measures spacing goes red. A named barrier survives a tidy-up; an implied one does not.
     *
     * Collapsing the rest is the ordinary half: a message that opens with three blank lines must
     * not produce an excerpt that is all spaces.
     */
    private function oneLine(string $value): string
    {
        $withoutBreaks = (string) preg_replace('/[\r\n]+/', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $withoutBreaks));
    }
}
