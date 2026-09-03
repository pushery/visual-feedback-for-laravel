{{--
    The admin report mail body. ALL user-influenced content renders INERT:
      - the free text (message, subject) sits in a fenced code block whose delimiter is computed
        from the content — MailCell::fence(). A HARDCODED ``` fence does not make text inert:
        CommonMark closes on the first line with at least as many backticks as opened it, so a
        reporter typing three backticks ended the block and everything after it rendered as live
        Markdown. Measured, not theorised — a link injected that way reached the rendered mail as
        a real <a href>. The fence is now one backtick longer than the longest run in the content;
      - context values and the technical-details cells go through MailCell (pipes escaped so a
        `Bob | Alice` value cannot shift the table columns — the "Undefined array key 1"
        crash — and newlines folded so a value cannot end a row/list item early);
      - Blade's {{ }} HTML-escapes those cells on top, and CommonMark does not auto-link bare URLs.

    THE TWO FENCED VALUES ARE ECHOED RAW, AND THAT IS DELIBERATE — they are the one place in this
    body where Blade escaping made things WORSE. A Markdown mailable does not echo through e():
    Markdown::render() installs `new EncodedHtmlString(%s)` as the echo format, which is right for
    prose (CommonMark resolves the entity again, so `A &amp; B` renders as `A & B`) and wrong
    inside a fence, where the spec says an entity is literal text. So every `&`, `<`, `>` and `"`
    a reporter typed was escaped twice and arrived as a visible entity —
    `the filter &quot;A &amp; B&quot; fails when x &lt; y` — in the package's own payload.

    Dropping the escape costs no inertness, because none of it ever came from there: CommonMark
    escapes code-block content on output (a `<script>` is served as `&lt;script&gt;`), and
    MailCell::fence() makes the delimiter one backtick longer than the longest run the reporter
    typed, so the block cannot be closed early. Both halves — the inertness and the fidelity —
    are held together by the package's own suite, and they have to be: a change that buys one at
    the cost of the other reads as an improvement in isolation.

    One accepted trade, in the text/plain alternative only: renderText() passes the whole rendered
    body through html_entity_decode(), so a reporter who types a literal `&amp;` sees `&` there.
    The HTML part, which is what a client displays, carries it correctly. The two pipelines want
    opposite inputs and no single string satisfies both; the trade is deliberate and pinned by
    its own test, so it cannot be "fixed" back into the other direction unnoticed.

    The category heading is the label from lang (trusted), resolved under the mail render locale.
--}}
<x-mail::message>
# {{ $categoryLabel }}

@if (! is_null($report->subject) && trim($report->subject) !== '')
**{{ __('visual-feedback::messages.mail.subject') }}**
{!! \Pushery\VisualFeedback\Channels\Mail\MailCell::fence($report->subject) !!}
{!! $report->subject !!}
{!! \Pushery\VisualFeedback\Channels\Mail\MailCell::fence($report->subject) !!}
@endif

**{{ __('visual-feedback::messages.mail.message') }}**
{!! \Pushery\VisualFeedback\Channels\Mail\MailCell::fence($report->message) !!}
{!! $report->message !!}
{!! \Pushery\VisualFeedback\Channels\Mail\MailCell::fence($report->message) !!}

{{-- Who reported it, when, and from which trigger.

     Name and email used to be omitted here, on the argument that the envelope carries them. It
     carries ONE of them, conditionally: Reply-To is the reporter's EMAIL, only while
     `mail.reply_to_reporter` is on and only when they gave an address — and `guests.require_email`
     ships false, so most guests give none. The NAME has no carrier at all. It is asked for and
     validated at 150 characters, and a host running the mail channel alone persists the report
     nowhere: what this template omits is gone the moment the mail is sent, per report.

     Every reporter-typed value here goes through MailCell::text(), not ::cell(). These sit in a
     LIVE-Markdown position — a bold line, a list item — and cell() escapes pipes but not links.
     Measured before this block existed: a phone value of `[click me](http://evil.example)`
     reached the rendered mail as a real anchor. --}}
**{{ __('visual-feedback::messages.mail.reporter') }}**

- **{{ __('visual-feedback::messages.mail.reporter_type') }}:** {{ $report->reporter->isGuest ? __('visual-feedback::messages.mail.reporter_guest') : __('visual-feedback::messages.mail.reporter_member') }}
@if (is_string($report->reporter->name) && trim($report->reporter->name) !== '')
- **{{ __('visual-feedback::messages.mail.reporter_name') }}:** {{ \Pushery\VisualFeedback\Channels\Mail\MailCell::text($report->reporter->name) }}
@endif
@if (is_string($report->reporter->email) && trim($report->reporter->email) !== '')
- **{{ __('visual-feedback::messages.mail.reporter_email') }}:** {{ \Pushery\VisualFeedback\Channels\Mail\MailCell::text($report->reporter->email) }}
@endif
@if (is_string($report->reporter->phone) && trim($report->reporter->phone) !== '')
- **{{ __('visual-feedback::messages.mail.phone') }}:** {{ \Pushery\VisualFeedback\Channels\Mail\MailCell::text($report->reporter->phone) }}
@endif
- **{{ __('visual-feedback::messages.mail.submitted_at') }}:** {{ $report->submittedAt->format(DATE_ATOM) }}
- **{{ __('visual-feedback::messages.mail.mode') }}:** {{ \Pushery\VisualFeedback\Channels\Mail\MailCell::text($report->mode) }}

@if (count($report->context) > 0)
**{{ __('visual-feedback::messages.mail.context') }}**

@foreach ($report->context as $entry)
- **{{ \Pushery\VisualFeedback\Channels\Mail\MailCell::cell($entry->label) }}:** {{ \Pushery\VisualFeedback\Channels\Mail\MailCell::cell($entry->value) }}
@endforeach
@endif

@if (count($report->attachments) > 0)
{{ trans_choice('visual-feedback::messages.mail.attachments', count($report->attachments), ['count' => count($report->attachments)]) }}
@endif

@if (count($report->metadata) > 0)
**{{ __('visual-feedback::messages.mail.technical_details') }}**

<x-mail::table>
| {{ __('visual-feedback::messages.mail.field') }} | {{ __('visual-feedback::messages.mail.value') }} |
| :--- | :--- |
@foreach ($report->metadata as $key => $value)
| {{ \Pushery\VisualFeedback\Channels\Mail\MailCell::cell(\Illuminate\Support\Str::headline((string) $key)) }} | {{ \Pushery\VisualFeedback\Channels\Mail\MailCell::value($value) }} |
@endforeach
</x-mail::table>
@endif

<x-mail::subcopy>
{{ __('visual-feedback::messages.mail.report_id') }}: {{ $report->id }}
</x-mail::subcopy>
</x-mail::message>
