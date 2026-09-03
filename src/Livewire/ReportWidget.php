<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Livewire;

use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Attachments\AttachmentPolicy;
use Pushery\VisualFeedback\Attachments\FilenameSanitizer;
use Pushery\VisualFeedback\Context\ContextRegistry;
use Pushery\VisualFeedback\Contracts\ResolvesReporter;
use Pushery\VisualFeedback\Data\ReportContextEntry;
use Pushery\VisualFeedback\Events\RejectionReason;
use Pushery\VisualFeedback\Metadata\MetadataSanitizer;
use Pushery\VisualFeedback\Privacy\PrivacyNotice;
use Pushery\VisualFeedback\Submission\SubmissionInput;
use Pushery\VisualFeedback\Submission\SubmitReport;
use Pushery\VisualFeedback\Submission\ValidationFailure;
use Pushery\VisualFeedback\Support\CategoryLabels;
use Pushery\VisualFeedback\Support\ClientConfig;
use Pushery\VisualFeedback\Support\Settings;
use Throwable;

/**
 * The feedback widget — a THIN shell over the transport-agnostic SubmitReport pipeline.
 * It binds the form, hands the pipeline a plain SubmissionInput, and reflects the
 * result. `mode` (modal | inline) is a per-instance override, Locked so the client can
 * never change it after mount. The reset lifecycle lets a reporter file a second report
 * in the same session; without a reset, a screenshot button that has been used once stays gone.
 */
class ReportWidget extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $mode = 'modal';

    /**
     * Per-instance category override. Empty falls back to the configured categories.
     * Locked so the client can never widen or swap the offered categories after mount.
     *
     * @var list<string>
     */
    #[Locked]
    public array $availableCategories = [];

    /**
     * Per-instance context entries, supplied ONLY as mount props by host code. Locked
     * so there is no client-callable path to set or widen the report's context — the
     * structural fix for that authorization defect. Each entry is a plain array
     * (key/label/value[/url/identifier]); the host authorizes what it puts here.
     *
     * @var list<array<string, mixed>>
     */
    #[Locked]
    public array $context = [];

    /**
     * Per-instance field overrides (e.g. ['subject' => false]) — Locked. A key present
     * here wins over config('visual-feedback.fields.*'); absent falls back to config.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $fields = [];

    /**
     * Per-instance mail recipient — Locked. Where this widget's reports go, overriding
     * `mail.to`: a docs page and a billing page in the same app can reach different teams.
     * Validated as an address in mount(), so an invalid one fails loudly at the call site
     * instead of silently swallowing every report from that page.
     */
    #[Locked]
    public ?string $recipient = null;

    /**
     * Per-instance capture switch — Locked. `null` follows the config default (which is
     * itself modal-only, see screenshotEnabled()); `false` removes the capture UI from this
     * widget even where the config allows it — a page showing someone else's data has good
     * reason to refuse screenshots regardless of the app-wide setting.
     *
     * Named `withScreenshot`, not `screenshot`: that name is already the uploaded capture
     * below, and one property cannot be both the switch and the file.
     */
    #[Locked]
    public ?bool $withScreenshot = null;

    public string $category = '';

    public ?string $subject = null;

    public string $message = '';

    public ?string $guestName = null;

    public ?string $guestEmail = null;

    public ?string $guestPhone = null;

    /**
     * Raw browser metadata collected client-side (url, viewport, language, …). UNTRUSTED
     * — the MetadataSanitizer enforces the allowlist, the length caps, and the never-IP
     * rule on submit, so a client can only ever inject allowlisted, capped values.
     *
     * @var array<string, mixed>
     */
    public array $metadata = [];

    /**
     * Honeypot — a hidden field a bot fills and a human never sees. Its name is deliberately
     * NON-semantic: a field named `website`/`email` is filled by browser autofill / password
     * managers even with autocomplete="off", so a real user with autofill would trip the trap
     * and their report would be silently dropped — a hidden field is not invisible to a password manager. A
     * non-empty value is a silent decoy rejection (success UI, nothing stored).
     */
    public string $feedbackReference = '';

    /**
     * Reporter file uploads. WithFileUploads holds these as TemporaryUploadedFile at the
     * UI boundary; submit() stores them and passes only PATHS onward (the arch guard keeps
     * the upload type from leaking past this component). Validated in real time against the
     * perimeter rules on every upload, and again server-side by the AttachmentValidator.
     *
     * @var array<int, mixed>
     */
    public array $attachments = [];

    /**
     * The client-captured screenshot, uploaded via the capture module's swappable
     * uploader ($wire.upload). Held as a TemporaryUploadedFile at the UI boundary,
     * stored to a PATH on submit; the ScreenshotValidator re-checks it server-side.
     */
    public ?TemporaryUploadedFile $screenshot = null;

    /**
     * The capture stage that produced the screenshot — `native` (pixel-exact getDisplayMedia)
     * or `dom` (the DOM-renderer reconstruction). Client-set via the capture module's onStage
     * seam, so it is UNTRUSTED: it is validated to the known set and recorded on the report
     * only when a screenshot was actually attached (see submit()). The recipient must be able
     * to tell an exact shot from a reconstruction.
     */
    public ?string $screenshotStage = null;

    public bool $privacyAcknowledged = false;

    public bool $submitted = false;

    public bool $failed = false;

    /**
     * How many submits have failed on this widget. The view moves focus to the offending
     * field when a submit fails, and an Alpine effect only re-runs when a value CHANGES —
     * `failed` goes false→true inside a single round-trip, so the client only ever observes
     * true→true and a second consecutive failure would move no focus at all. A counter
     * changes on every failure, so the focus move survives repetition.
     */
    public int $failureCount = 0;

    /**
     * Which field the last failure belongs to — `privacy` when the guest privacy
     * acknowledgment is missing, otherwise `message`. Without it every failure focused the
     * message field, including the one caused by an unticked privacy checkbox further down
     * the form: the reporter was dropped on a control that had nothing to do with the error.
     */
    public ?string $failedField = null;

    /** The reason in the reporter's words, when the pipeline named one; null falls back to the generic line. */
    public ?string $failedMessage = null;

    /**
     * Whether the field named above is genuinely INVALID — as opposed to merely being where the
     * reporter's attention should go.
     *
     * ⚠️ These are two different questions and the obvious shortcut gets them wrong.
     * `$failedField` is a FOCUS TARGET: three paths set it to `message` while the message the
     * reporter wrote is perfectly fine — a listener veto, a rate limit, and the master switch
     * being off (which fabricates a failure on `message` so the operator's line has somewhere to
     * land). Marking that control `aria-invalid` would tell a screen reader the text is wrong
     * because the widget is switched off.
     *
     * So the discrimination happens where the reason is still known: only
     * `RejectionReason::Validation` — and the privacy acknowledgment, which is a field error the
     * reporter can fix on the spot — set this. Everything else leaves it false and the message
     * stays in the shared alert region, unattributed, which is the honest place for it.
     */
    public bool $failedFieldInvalid = false;

    /**
     * The SERVER-anchored time the form was opened (Unix seconds), stamped on mount. #[Locked]
     * so the client cannot rewrite it — the abuse time trap needs a start it can trust, and a
     * plain public property is client-modifiable (a bot could fake a slow fill). 0 until mounted.
     */
    #[Locked]
    public int $openedAt = 0;

    /**
     * Client-supplied challenge data, bound by whatever markup `abuse.challenge_view` renders.
     *
     * Deliberately NOT #[Locked]: the whole point is that the browser writes into it. That makes
     * every value here attacker-controlled, which is exactly what a challenge token is — the gate
     * verifying it treats it as a claim to check, never as a fact.
     *
     * Nothing else in this package reads it, so a hostile value cannot reach anything of ours: it
     * is never stored, queued, mailed, logged or rendered. What it CAN do is make a gate throw,
     * and an additional gate that throws fails OPEN by design (AbuseGateManager) — leaving the
     * always-on floor, which is the protection every install has anyway, plus a warning in the
     * log. A gate that verifies a token should therefore treat a surprising shape as a failed
     * challenge rather than let it become an exception.
     *
     * Typed `mixed`, not `scalar|null`: Livewire hydrates whatever the client sends and nothing
     * coerces it, so the narrower annotation would be a promise the runtime does not keep — and
     * PHPStan honors annotations, so a gate author would be handed a guarantee a request can
     * break. `$metadata` above carries the wide type for the same reason.
     *
     * @var array<string, mixed>
     */
    public array $challenge = [];

    /**
     * @param  list<string>  $categories  per-instance category override (empty = config default)
     * @param  list<array<string, mixed>>  $context  host-built instance context entries
     * @param  array<string, mixed>  $fields  per-instance field overrides (e.g. ['subject' => false])
     * @param  ?string  $recipient  per-instance mail recipient, overriding `mail.to`
     * @param  ?string  $mode  `modal` or `inline`; null takes the default from `ui.trigger`
     * @param  ?bool  $withScreenshot  per-instance capture switch (null = the modal-only default)
     */
    public function mount(?string $mode = null, array $categories = [], array $context = [], array $fields = [], ?string $recipient = null, ?bool $withScreenshot = null): void
    {
        if ($recipient !== null && filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            // A developer-supplied prop: fail at the call site rather than silently mailing
            // reports into nowhere. FILTER_VALIDATE_EMAIL also rejects the newline that
            // would turn this into a mail-header injection.
            throw new InvalidArgumentException('visual-feedback: the recipient mount prop must be a valid email address.');
        }

        $this->recipient = $recipient;
        $this->withScreenshot = $withScreenshot;
        // `ui.trigger = inline` is documented as "no modal; the form is part of the page", and it
        // has to actually DO that. It used to be read in exactly one place — a comparison against
        // `'fab'` that decided whether to render the floating button — which made `inline` and
        // `none` byte-for-byte identical: both suppressed the button and left a modal with no way
        // to open it. Setting `inline` therefore produced a widget nobody could reach.
        //
        // The mount prop still wins, so a host placing two widgets on one page can mix them; the
        // config only supplies the default. Hence a NULL default rather than 'modal' — with a
        // string default there is no way to tell "not passed" from "passed modal".
        $this->mode = $mode ?? (config('visual-feedback.ui.trigger') === 'inline' ? 'inline' : 'modal');
        $this->availableCategories = array_values(array_filter($categories, is_string(...)));
        $this->context = array_values(array_filter($context, is_array(...)));
        $this->fields = $fields;
        $this->category = $this->firstCategory();
        // The inline widget is open from the moment it renders, so mount IS its open — and
        // nothing else will ever stamp it, because the open listener is modal-only. A modal is
        // NOT open at mount: markOpened() stamps it when the panel actually opens.
        //
        // The difference is not cosmetic. Stamping every mount put a second-resolution timestamp
        // into the rendered markup of every page carrying the widget, so the same page was
        // byte-different from one second to the next — enough to defeat any full-page cache or
        // ETag a host puts in front of it. A modal that is never opened now renders identically
        // all day.
        //
        // This moves WITH the abuse floor, not before it: BuiltinAbuseGate refuses a submission
        // that carries no open time while the trap is armed, so the unstamped modal is refused
        // rather than exempted.
        $this->openedAt = $this->mode === 'inline' ? Carbon::now()->getTimestamp() : 0;
    }

    /**
     * Re-anchor the time trap to the moment the modal OPENED, from the server clock.
     *
     * Anchoring at mount alone is inert for the deployment this package documents: a widget
     * kept alive in `@persist` across `wire:navigate` mounts ONCE, at page load. The trap
     * would then measure "time since the visitor arrived on the site" — anyone who read a
     * page for a few seconds before opening the form clears `min_fill_seconds` without ever
     * having seen it, which is exactly the bot behavior it exists to catch.
     *
     * `openedAt` stays #[Locked], so this action is the only thing that can move it and it
     * only ever moves it to the server's own time — a client can call it, but calling it is
     * indistinguishable from opening the form, and every call makes the trap STRICTER.
     */
    public function markOpened(): void
    {
        $this->openedAt = Carbon::now()->getTimestamp();
    }

    /**
     * Real-time upload perimeter: every uploaded file is validated the MOMENT it lands
     * (not only at submit), against the MIME allowlist and byte cap derived from config
     * via the AttachmentPolicy, and the count against max_files. An unacceptable file
     * surfaces an error immediately instead of riding to the submit.
     */
    public function updatedAttachments(): void
    {
        $policy = app(AttachmentPolicy::class);

        $this->validate([
            'attachments' => ['array', 'max:'.$policy->maxFiles()],
            'attachments.*' => ['file', 'mimes:'.$policy->ruleExtensions(), 'max:'.$policy->maxFileKilobytes()],
        ]);
    }

    /** Remove one queued attachment by index — bounds-checked, never trusting the client index. */
    public function removeAttachment(int $index): void
    {
        if (array_key_exists($index, $this->attachments)) {
            unset($this->attachments[$index]);
            $this->attachments = array_values($this->attachments);
        }
    }

    /**
     * Real-time perimeter for the captured screenshot: it must be a PNG within the byte
     * cap the moment it lands, so a tampered upload dies at the endpoint, not only at
     * submit (where the ScreenshotValidator re-checks it server-side).
     *
     * A REFUSED capture is dropped from the component here, and that is not cosmetic. The
     * property used to keep the rejected file: `discard()` and `retake()` only reset the Alpine
     * state machine in the browser, so nothing on the client could clear it, and the next submit
     * stored the refused capture, had the ScreenshotValidator refuse it a second time, and
     * rejected the whole report. The reporter was left with a widget that looked empty and a
     * submission that failed on a screenshot they could not see — the "capture -> reject loop
     * with no way out" the shipped config warns about, one layer down.
     *
     * The clear has to run BEFORE the exception leaves this method, which is why the validation
     * is caught and re-thrown rather than left to fly: `validate()` throws on the failing line,
     * and every statement after it belongs to a path that a rejection never takes. Re-throwing
     * the SAME exception keeps the error bag exactly as it was — Livewire's SupportValidation
     * catches it, fills the bag and stops propagation, so the reporter still reads the perimeter's
     * own message next to the capture control.
     */
    public function updatedScreenshot(): void
    {
        if ($this->screenshot instanceof TemporaryUploadedFile) {
            $maxBytes = is_numeric($b = config('visual-feedback.screenshot.max_bytes')) ? (int) $b : 8 * 1024 * 1024;

            try {
                $this->validate([
                    'screenshot' => ['image', 'mimes:png', 'max:'.max(1, intdiv($maxBytes, 1024))],
                ]);
            } catch (ValidationException $exception) {
                $this->screenshot = null;

                throw $exception;
            }
        }
    }

    public function submit(SubmitReport $submit, ResolvesReporter $reporter): void
    {
        $this->failed = false;

        // The master switch, read FIRST and then used to skip every step below that costs
        // something. The verdict itself still comes from SubmitReport::handle(), which refuses on
        // the same switch, dispatches ReportRejected and names the message the reporter reads —
        // this is not a second gate, it is the widget declining to pay for a submission the
        // pipeline is certain to refuse. Settings::enabled() is a plain config read, so asking it
        // here and again there costs nothing and the two can never disagree.
        //
        // The shipped config and the pipeline's own docblock both promise that a disabled package
        // "refuses every request before it touches a rate limiter, a cache or a disk", and with an
        // upload attached that was false: the stores below ran unconditionally, so a submission
        // into a switched-off widget copied up to five attachments plus the capture onto the
        // permanent disk and only then did the pipeline read `visual-feedback.enabled`. Nothing
        // accumulated — the rejection discards them again — but an attacker paid a write and a
        // delete per request, on S3 a billed PUT and DELETE, against a form the operator had
        // turned off, and `/livewire/update` carries no throttle in front of it.
        $enabled = $this->settings()->enabled();

        $reporterDto = $reporter->resolve($this->guestName, $this->guestEmail, $this->guestPhone);

        // A guest must acknowledge the privacy notice, when one is configured, before submitting.
        //
        // Behind the switch for the "cache" half of that promise: `privacy.source = legal-consent`
        // resolves the notice by READING the published document through the bridge, and a disabled
        // package has no business reading anything. A guest who has not acknowledged now gets the
        // operator's "the form is off" instead of a privacy error, which is the more accurate of
        // the two answers anyway — the acknowledgment is not what stopped the report.
        if ($enabled && $reporterDto->isGuest && ! $this->privacyAcknowledged && app(PrivacyNotice::class)->required()) {
            // Named like every other failure. This was the last one that still fell back to the
            // generic line, and it is a rejection the reporter can act on immediately.
            $this->fail('privacy', (string) trans('visual-feedback::messages.validation.privacy_required'), invalid: true);

            return;
        }

        // Sanitize the untrusted CLIENT metadata first, then fold in the trusted capture
        // stage — added AFTER sanitization and off the client-controlled allowlist, so it can
        // never be spoofed from the browser and only rides when a screenshot was attached.
        $metadata = app(MetadataSanitizer::class)->sanitize($this->metadata, request()->userAgent());

        if ($this->screenshot instanceof TemporaryUploadedFile && in_array($this->screenshotStage, ['native', 'dom'], true)) {
            $metadata['capture_method'] = $this->screenshotStage;
        }

        // Which published notice this acknowledgment belongs to. Added AFTER sanitization, from a
        // server-side read — the reporter's browser never sends any of it, and MetadataSanitizer
        // strips the reserved prefix unconditionally so it cannot be forged even if a consuming
        // application adds one of these keys to `metadata.collect`.
        //
        // Read at SUBMIT, not carried from render: component state makes a round trip through the
        // browser, and provenance that the client could return is not provenance. The README names
        // the consequence — publish a new version while a widget is open and the recorded version
        // is the newer one — and, like the acknowledgment check above, not read at all while the
        // package is switched off, because with the legal-consent source this is the second call
        // that reaches for a published document.
        $metadata = $enabled
            ? array_merge($metadata, app(PrivacyNotice::class)->wording()?->toMetadata() ?? [])
            : $metadata;

        // Hoisted out of the argument list so the paths still exist AFTER the call. They used to
        // be evaluated inline, which meant the files were on the permanent disk before the abuse
        // floor had run and nothing knew where they were once it rejected.
        //
        // Behind the master switch as well, and this is the step the promise is really about:
        // storing is the only thing in this method that WRITES.
        //
        // The ABUSE floor deliberately stays where it is. Moving it ahead of the store would mean
        // either running the gate here as well — BuiltinAbuseGate::check() hits the rate limiter,
        // so a second call burns a second token against the reporter's own quota — or splitting
        // handle() into a two-phase public API, which would put a seam into the one
        // transport-agnostic entrance a second adapter could call in the wrong order. A
        // gate-rejected submission therefore still writes and then discards; that residue is
        // reclaimed on the same request and, failing that, by the orphan sweep.
        $attachmentPaths = $enabled ? $this->storeAttachments() : [];
        $screenshotPath = $enabled ? $this->storeScreenshot() : null;

        $result = $submit->handle(new SubmissionInput(
            category: $this->category,
            subject: $this->subject,
            message: $this->message,
            reporter: $reporterDto,
            mode: $this->mode,
            context: app(ContextRegistry::class)->collect($this->instanceContext()),
            metadata: $metadata,
            attachmentPaths: $attachmentPaths,
            screenshotPath: $screenshotPath,
            honeypot: $this->feedbackReference,
            ipAddress: request()->ip(),
            formOpenedAt: $this->openedAt > 0 ? new DateTimeImmutable('@'.$this->openedAt) : null,
            recipient: $this->recipient,
            challenge: $this->challenge,
        ));

        // Reclaim the files of a submission that produced no report.
        //
        // Keyed on `accepted`, NOT on `showsSuccess`: a honeypot hit shows the decoy success and
        // stores nothing, so keying on the UI flag would leak exactly the files a bot uploads.
        // That is the case this matters for — a public form, and an attacker who fails the floor
        // on purpose while the disk fills.
        //
        // The age-based orphan sweep would eventually reclaim these, but "eventually" is a nightly
        // command a consumer has to schedule, and a bot does not wait for it.
        if (! $result->accepted) {
            $this->discardStoredFiles($screenshotPath === null ? $attachmentPaths : [$screenshotPath, ...$attachmentPaths]);
        }

        if ($result->showsSuccess) {
            $this->submitted = true;

            return;
        }

        // The field the pipeline named, mapped from its domain key to this widget's own. An
        // unmapped key falls back to the message box rather than pointing nowhere.
        $failure = $result->failure;

        $this->fail(
            $failure instanceof ValidationFailure ? self::FIELD_OF_KEY[$failure->field] ?? 'message' : ('message'),
            $failure?->message,
            // Only a real validation rejection marks a control invalid. `Disabled` fabricates a
            // failure on `message` to carry the operator's line, and a rate limit or a listener
            // veto name no field at all -- all three land on the message box as a focus target,
            // and none of them means the reporter typed something wrong.
            invalid: $result->rejectionReason === RejectionReason::Validation,
        );
    }

    /** The attachment caps as one sentence, pluralized on the file count. */
    private function attachmentLimitLine(): string
    {
        $policy = app(AttachmentPolicy::class);

        return trans_choice(
            'visual-feedback::messages.widget.attachment_limit',
            $policy->maxFiles(),
            ['count' => $policy->maxFiles(), 'size' => $policy->maxFileSizeForHumans()],
        );
    }

    /**
     * Domain validation key → the widget field the reporter sees. The pipeline speaks in its own
     * keys (`guest_email`); the view needs the control (`email`).
     */
    private const array FIELD_OF_KEY = [
        'category' => 'category',
        'subject' => 'subject',
        'message' => 'message',
        'guest_name' => 'name',
        'guest_email' => 'email',
        'guest_phone' => 'phone',
        'attachments' => 'files',
        'screenshot' => 'files',
    ];

    /**
     * Whether this widget shows the capture UI.
     *
     * The per-instance switch wins outright. Otherwise the default is modal-only: an inline
     * widget is part of a page — a contact form in a footer — and a screenshot button there
     * invites a capture of a page the reporter is not reporting on.
     */
    private function screenshotEnabled(): bool
    {
        if (config('visual-feedback.screenshot.strategy') === 'off') {
            return false;
        }

        return $this->withScreenshot ?? $this->mode === 'modal';
    }

    /**
     * Record a failed submit so the view can announce it and move focus to the field it belongs to.
     *
     * `$message` is the reason in the reporter's words where the pipeline knew one. Without it the
     * view falls back to the generic line — which is what EVERY failure used to show, whatever had
     * gone wrong.
     */
    /**
     * The package settings accessor.
     *
     * Resolved per call instead of held as a property: Livewire serializes a component's public
     * state between requests, and a service object is not state — holding one here would make
     * hydration carry it, or quietly drop it and leave a null on the second request.
     */
    private function settings(): Settings
    {
        return app(Settings::class);
    }

    private function fail(string $field, ?string $message = null, bool $invalid = false): void
    {
        $this->failed = true;
        $this->failedField = $field;
        $this->failedMessage = $message;
        $this->failedFieldInvalid = $invalid;
        $this->failureCount++;
    }

    /** Full reset so a second report (including a fresh screenshot) is possible in the same session. */
    public function resetWidget(): void
    {
        $this->reset(['category', 'subject', 'message', 'guestName', 'guestEmail', 'guestPhone', 'attachments', 'screenshot', 'screenshotStage', 'metadata', 'privacyAcknowledged', 'feedbackReference', 'challenge', 'submitted', 'failed', 'failedField', 'failedMessage', 'failedFieldInvalid']);
        // reset() restores the PROPERTY default — the empty string — which would put the
        // widget back into the mismatch mount() resolves. The second report must start where
        // the first one did.
        $this->category = $this->firstCategory();

        // A challenge token is single-use by construction, so carrying one into the next report
        // is worse than having none: the provider rejects a replayed token and the gate rejects a
        // reporter who did nothing wrong. Clearing it is only half the answer, though — the form
        // is removed on success (`@unless ($submitted)`), which tears the challenge region out
        // with it, and the third-party script that mounts the widget does not run again when the
        // region comes back. So the host is TOLD, and can re-initialize.
        //
        // The event name is public API: it can be added for free today and never silently later.
        $this->dispatch('visual-feedback:challenge-reset');
    }

    /**
     * The category the picker SHOWS before the reporter touches it.
     *
     * The picker has no placeholder option, so a browser selects its first option and shows
     * it as chosen. The server held an empty string, so a reporter who agreed with what they
     * saw — the natural thing to do — submitted an empty category and was told "Something
     * went wrong": found by submitting through a real Laravel app, not a fixture. The suite
     * could not see it, because Livewire's test harness assigns properties directly and never
     * renders a <select> for a browser to apply that default to.
     *
     * So the server adopts what the UI shows. The alternative — a placeholder option forcing
     * an explicit choice — is a product decision with a seven-locale string behind it, filed
     * separately; this is the part that is simply a defect.
     */
    private function firstCategory(): string
    {
        return (string) (array_key_first($this->categoryOptions()) ?? '');
    }

    /**
     * Store the captured screenshot to the configured PRIVATE disk under its own random
     * subdir, returning its PATH (or null when there is no screenshot). The
     * TemporaryUploadedFile never travels past this component.
     */
    /**
     * Delete files stored for a submission that produced no report.
     *
     * Deliberately best-effort and silent: this runs on a path that is ALREADY a rejection, and a
     * disk error here must not turn a clean "your message was too long" into an exception the
     * reporter sees. What it must not do is claim success — the orphan sweep still walks the same
     * directory, so anything missed here is reclaimed later rather than lost.
     *
     * @param  list<string>  $paths
     */
    private function discardStoredFiles(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $disk = is_string($d = config('visual-feedback.attachments.disk')) ? $d : 'local';

        try {
            Storage::disk($disk)->delete($paths);
        } catch (Throwable) {
            // Left to the orphan sweep, which exists for exactly this residue.
        }
    }

    private function storeScreenshot(): ?string
    {
        if (! $this->screenshot instanceof TemporaryUploadedFile) {
            return null;
        }

        $disk = is_string($d = config('visual-feedback.attachments.disk')) ? $d : 'local';
        // Through the policy, NOT off the raw config. The policy trims slashes and maps an empty
        // string onto the default; a raw read accepts `''` as a directory, and the screenshot then
        // lands at `/screenshots/...` while the orphan sweep walks `visual-feedback/` and the
        // uploads sit under it. The file becomes unreachable to the only thing that would ever
        // reclaim it — permanently, and silently, because nothing errors.
        //
        // AttachmentPolicy::directory()'s own docblock records this exact bug being fixed once
        // before, across three readers. This method was the fourth and was missed.
        $directory = app(AttachmentPolicy::class)->directory();
        $subdir = pathinfo($this->screenshot->hashName(), PATHINFO_FILENAME);

        $stored = $this->screenshot->storeAs($directory.'/screenshots/'.$subdir, 'screenshot.png', ['disk' => $disk]);

        return is_string($stored) ? $stored : null;
    }

    /**
     * Store each queued upload to the configured PRIVATE disk under its own random subdir
     * (so two files with the same name never collide), keeping the sanitized client name as
     * the readable basename — which is also the mail attachment name. Returns only paths;
     * the TemporaryUploadedFile never travels onward.
     *
     * The basename stays the reporter's, the EXTENSION does not. `guessExtension()` resolves it
     * from the server-sniffed MIME — the same value Livewire's `mimes:` perimeter just accepted
     * the file on — so the stored key and the mail attachment finally say the same thing about
     * the bytes that every check in the package does. Until now the name was the client's whole
     * string: a valid PNG uploaded as `report.html` passed the perimeter on its content, passed
     * the AttachmentValidator's finfo re-check on its content, and then landed in the
     * maintainer's inbox as `report.html` — a document their browser will happily execute once
     * they save and open it, from a mail that appears to come from their own tooling. This is the
     * same defense storeScreenshot() has always had two methods up, where the name is simply
     * fixed at `screenshot.png`.
     *
     * @return list<string>
     */
    private function storeAttachments(): array
    {
        $disk = is_string($d = config('visual-feedback.attachments.disk')) ? $d : 'local';
        $directory = app(AttachmentPolicy::class)->directory();
        $sanitizer = app(FilenameSanitizer::class);

        $paths = [];

        foreach ($this->attachments as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $name = $sanitizer->sanitize($file->getClientOriginalName(), $file->hashName(), $file->guessExtension());
                $subdir = pathinfo($file->hashName(), PATHINFO_FILENAME);
                $stored = $file->storeAs($directory.'/'.$subdir, $name, ['disk' => $disk]);

                if (is_string($stored)) {
                    $paths[] = $stored;
                }
            }
        }

        return $paths;
    }

    /**
     * The configured challenge view, or null — and a WARNING rather than silence when it is
     * configured and missing.
     *
     * `@includeIf` renders nothing for a view that does not exist, which is the correct template
     * behavior and the wrong operational one. A host who typos the view name gets a form with no
     * challenge in it, a gate that rejects every reporter for a token that could never be
     * produced, and — because a rejection is silent by default — a decoy success screen while
     * every report is lost. Nothing anywhere would say why.
     *
     * This is the same misconfiguration the package already refuses to swallow one layer down:
     * AbuseGateRegistry logs when `abuse.driver` names a gate nobody registered. Two adjacent
     * settings, one typo away from the same outcome, should not answer differently.
     */
    private function resolvedChallengeView(): ?string
    {
        $view = $this->settings()->challengeView();

        if ($view === null || view()->exists($view)) {
            return $view;
        }

        app(LoggerInterface::class)->warning(
            'visual-feedback: the configured abuse challenge view does not exist — no challenge is rendered',
            ['view' => $view, 'setting' => 'visual-feedback.abuse.challenge_view'],
        );

        // Rendering the empty region would leave a wire:ignore div that says nothing to anyone.
        return null;
    }

    public function render(): View
    {
        // The master switch, drawing half. `visual-feedback.enabled` is documented in the shipped
        // config as "the widget renders nothing and the submit endpoint rejects everything", and
        // until now it did neither: Settings::enabled() had no caller anywhere in the package.
        //
        // Short-circuiting HERE rather than inside the template covers both view trees at once —
        // the WireKit tree is a publish-time override of this same view name, so a guard written
        // in one file would be absent from the other the moment a host publishes.
        //
        // This is the cosmetic half. The one that holds is in SubmitReport::handle(), because a
        // Livewire component registered by name is reachable without the page that renders it.
        if (! $this->settings()->enabled()) {
            return view('visual-feedback::livewire.disabled');
        }

        // Resolved once, because both are asked twice below and the second question is expensive.
        // Together they are the condition BOTH view trees put the whole privacy block behind
        // (`@if ($showGuestFields && $privacyNoticeUrl)`), and the wording lookup underneath it can
        // be a database read: with `privacy.source = legal-consent` it goes through the bridge to
        // the published document, uncached, on every render — including the renders of an
        // authenticated reporter, who never sees the block. So the condition is evaluated here
        // instead of paying for a value the templates will discard.
        $isGuest = app(ResolvesReporter::class)->resolve()->isGuest;
        $privacyNoticeUrl = app(PrivacyNotice::class)->url();

        return view('visual-feedback::livewire.report-widget', [
            'categoryOptions' => $this->categoryOptions(),
            // What the reporter may attach, in words, next to the field. Built ONCE here and
            // handed to both view trees: two trees rendering their own trans_choice would be two
            // places to keep in step, and the numbers come from the policy so they cannot drift
            // from `accept` or the server rules.
            'attachmentLimit' => $this->attachmentLimitLine(),
            // The challenge view, or null. Resolved HERE for the same reason as the master switch
            // above: both view trees render this same component, so a lookup written into one
            // template would be missing from the other the moment a host publishes.
            'challengeView' => $this->resolvedChallengeView(),
            // Guest identity fields show only when there is no authenticated reporter —
            // decided by the same resolver the submission uses, so the two never disagree.
            'showGuestFields' => $isGuest,
            // The subject field is optional per config, overridable per instance.
            'showSubject' => $this->fieldEnabled('subject'),
            // The phone field is off by default; shown only to a guest when enabled.
            'showPhone' => $this->fieldEnabled('phone') && $isGuest,
            // The privacy notice URL a guest must acknowledge, or null when none is set. This one
            // value decides both whether the block renders and whether submit() demands the tick —
            // see PrivacyNotice::required().
            'privacyNoticeUrl' => $privacyNoticeUrl,
            // The sentence for that checkbox when the source supplies one (the legal-consent
            // bridge hands over the PUBLISHED acknowledgment wording), else null and the views
            // fall back to this package's own lang line. Plain text, escaped by the templates:
            // legal-consent never runs this field through its sanitizer.
            //
            // Asked ONLY when the block that would show it renders. The named side effect: a
            // wording source that logs a fallback warning no longer logs one on an authenticated
            // reporter's render — right for a guest-only checkbox, and a change in behavior.
            // Written on ONE line deliberately: a `: null,` on a line of its own compiles to no
            // opcode, so pcov never records it while PHPUnit counts it as executable — permanently
            // red under this package's 100% floor. CoverableSourceLineTest holds that rule.
            'privacyNoticeWording' => $isGuest && $privacyNoticeUrl !== null ? app(PrivacyNotice::class)->wording()?->text : null,
            // Max message length for the live counter. Code points here match the
            // server's mb_strlen-based validation, so the two never disagree.
            'messageMax' => is_numeric($max = config('visual-feedback.fields.message.max_length')) ? (int) $max : 50_000,
            // FAB corner for the built-in modal trigger.
            'fabPosition' => is_string($pos = config('visual-feedback.ui.position')) ? $pos : 'bottom-right',
            // The built-in FAB renders only for a modal widget whose trigger is `fab`.
            // With `none`/`inline` the host places its own <x-visual-feedback::trigger>
            // or <x-visual-feedback::fab>, keeping exactly one trigger per instance.
            'showFab' => $this->mode === 'modal' && config('visual-feedback.ui.trigger') === 'fab',
            // App locale for the client counter's Intl.NumberFormat — never a hardcoded
            // literal, so the thousands separator follows the reporter's language.
            'appLocale' => app()->getLocale(),
            // The file picker's `accept` attribute, DERIVED from the same server MIME
            // allowlist the validation uses — so the two can never drift apart.
            'acceptAttribute' => app(AttachmentPolicy::class)->acceptAttribute(),
            'screenshotEnabled' => $this->screenshotEnabled(),
            // Client-capture options for the Alpine state machine — the single ClientConfig
            // source, so the widget and the <x-visual-feedback::scripts> island never drift.
            'screenshotCaptureConfig' => ClientConfig::screenshot(),
        ]);
    }

    /**
     * The configured categories mapped to their labels, for the category picker.
     *
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        $labels = app(CategoryLabels::class);

        $keys = $this->availableCategories !== []
            ? $this->availableCategories
            : (is_array($configured = config('visual-feedback.categories')) ? $configured : []);

        $options = [];

        foreach ($keys as $key) {
            if (is_string($key)) {
                $options[$key] = $labels->label($key);
            }
        }

        return $options;
    }

    /**
     * Rebuild the Locked instance-context prop into value objects. Only well-formed
     * entries (string key/label/value) survive; the host owns what it puts in the prop.
     *
     * @return list<ReportContextEntry>
     */
    private function instanceContext(): array
    {
        $entries = [];

        foreach ($this->context as $entry) {
            $key = $entry['key'] ?? null;
            $label = $entry['label'] ?? null;
            $value = $entry['value'] ?? null;

            if (is_string($key) && is_string($label) && is_string($value)) {
                $url = $entry['url'] ?? null;
                $identifier = $entry['identifier'] ?? null;

                $entries[] = new ReportContextEntry(
                    $key,
                    $label,
                    $value,
                    is_string($url) ? $url : null,
                    is_string($identifier) ? $identifier : null,
                );
            }
        }

        return $entries;
    }

    /**
     * Whether a field is enabled: a per-instance override wins over the config default.
     */
    private function fieldEnabled(string $field): bool
    {
        if (array_key_exists($field, $this->fields)) {
            return $this->fields[$field] !== false;
        }

        return config("visual-feedback.fields.{$field}.enabled", true) !== false;
    }
}
