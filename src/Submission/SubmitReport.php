<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Submission;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Pushery\VisualFeedback\Abuse\ReportAttempt;
use Pushery\VisualFeedback\Attachments\AttachmentValidator;
use Pushery\VisualFeedback\Attachments\ScreenshotValidator;
use Pushery\VisualFeedback\Channels\ChannelRegistry;
use Pushery\VisualFeedback\Contracts\AbuseGate;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Data\Reporter;
use Pushery\VisualFeedback\Events\RejectionReason;
use Pushery\VisualFeedback\Events\ReportRejected;
use Pushery\VisualFeedback\Events\ReportSubmitted;
use Pushery\VisualFeedback\Events\ReportSubmitting;
use Pushery\VisualFeedback\Events\ScreenshotAttached;
use Pushery\VisualFeedback\Support\FieldLabel;
use Pushery\VisualFeedback\Support\HeaderSafeEmail;
use Pushery\VisualFeedback\Support\Settings;

/**
 * The transport-agnostic submit pipeline. The Livewire component (and any future
 * frontend adapter) is a thin shell over this: it takes a SubmissionInput and returns
 * a SubmissionResult, knowing nothing about Livewire or the request. That makes the
 * whole pipeline testable without a UI and keeps the seam open for other adapters.
 *
 * Pipeline order, fixed:
 *   0. the master switch — `visual-feedback.enabled`. It runs before everything, including the
 *      abuse floor, because a disabled package must not touch a rate limiter, a cache or a disk,
 *      and because this is the endpoint half of a promise the shipped config makes: switch it
 *      off and the widget renders nothing and the submit path rejects everything,
 *   1. abuse floor — the always-on AbuseGate (honeypot + time trap + rate limits) runs
 *      before validation, so a failing attempt still burns a rate-limit token,
 *   2. build the report with its stable UUID,
 *   3. ReportSubmitting — synchronous listeners may cancel,
 *   4. validate,
 *   5. dispatch to channels,
 *   6. ReportSubmitted.
 */
final readonly class SubmitReport
{
    public function __construct(
        private Dispatcher $events,
        private Factory $validator,
        private Repository $config,
        private AbuseGate $abuse,
        private AttachmentValidator $attachments,
        private ScreenshotValidator $screenshots,
        private ChannelRegistry $channels,
        private Settings $settings,
    ) {}

    public function handle(SubmissionInput $input): SubmissionResult
    {
        // 0. Master switch. First, ahead of the abuse floor: a disabled package must not burn a
        // rate-limit token, warm a cache or touch a disk, and an operator who switched it off
        // during an incident has to be able to rely on that.
        //
        // The check lives here rather than only in the component, because the component is
        // registered by name and is therefore reachable without the page that renders it — a
        // stale tab, or anything that can address a Livewire component, still arrives at this
        // method. A switch enforced only where it is drawn is not a switch.
        //
        // Visible rather than silent, unlike the honeypot: this is an operator state, not a
        // trap. A person whose page was open when the switch flipped deserves to be told the
        // form is off rather than shown a success screen for a report nobody received.
        if (! $this->settings->enabled()) {
            $this->events->dispatch(new ReportRejected(RejectionReason::Disabled));

            return SubmissionResult::rejected(
                RejectionReason::Disabled,
                new ValidationFailure('message', (string) trans('visual-feedback::messages.widget.disabled')),
            );
        }

        // 0b. Sign-in only, when the host asked for it. Before the abuse floor on purpose: a guest
        // on an authenticated-only install is not traffic to be measured, they are traffic that
        // was never invited, and spending a rate-limit token on them would let a bot drain the
        // bucket of whichever subject it is keyed on.
        //
        // This is the half that holds. The five component templates and the widget's render()
        // also ask, and all of those are drawing: the component is registered by name, so a stale
        // tab or anything that can address a Livewire component still arrives here.
        //
        // Its own reason rather than a validation error, because a host reading a log needs to
        // tell "somebody left a required field empty" from "somebody submitted to a form they
        // were never shown". Visible, like the master switch and unlike the honeypot: the person
        // who reaches this is usually somebody whose session expired while the tab was open, and
        // a decoy success would tell them a report was received that was discarded.
        if ($this->settings->requiresAuthentication() && $input->reporter->isGuest) {
            $this->events->dispatch(new ReportRejected(RejectionReason::AuthenticationRequired));

            return SubmissionResult::rejected(
                RejectionReason::AuthenticationRequired,
                new ValidationFailure('message', (string) trans('visual-feedback::messages.widget.authentication_required')),
            );
        }

        // 1. Abuse floor — the always-on gate runs before validation, so a failing attempt
        // still burns a rate-limit token. A filled honeypot or a too-fast fill
        // is a silent decoy (nothing stored, a bot learns nothing); a rate limit is a visible
        // rejection so a real user gets feedback — and the gate says which of the two it is, so a
        // consumer's interactive challenge can be visible too. ReportRejected fires either way for
        // observability.
        $decision = $this->abuse->check(new ReportAttempt(
            reporter: $input->reporter,
            honeypot: $input->honeypot,
            ipAddress: $input->ipAddress,
            formOpenedAt: $input->formOpenedAt,
            submittedAt: Carbon::now()->toImmutable(),
            challenge: $input->challenge,
        ));

        if ($decision->reason instanceof RejectionReason) {
            $this->events->dispatch(new ReportRejected($decision->reason, $decision->detail));

            // The gate decides whether the reporter is told, not this line. It used to compare
            // the reason against one hardcoded case, which silently made every reason a consumer's
            // own gate could return — `ChallengeFailed` above all — render the decoy success
            // screen. A person who fails an interactive challenge would have been shown "thanks,
            // sent" for a report that was never sent.
            if ($decision->visible) {
                return SubmissionResult::rejected($decision->reason);
            }

            // A silent rejection answers with the success screen, which is the right answer to a
            // bot and the wrong one to the single human case that reaches it: open the widget,
            // press send with nothing typed, and read a confirmation for a report that never
            // left. Whether the trap or the required field would have refused first is a matter
            // of seconds the reporter cannot see, and the confirmation is what stops them from
            // trying again.
            //
            // Naming the empty field gives a bot nothing it does not already have: `required`
            // stands in the markup it just read, and the category allowlist is rendered as the
            // picker's own options. A submission that is complete and too fast still gets the
            // decoy, so the trap keeps every case it was built for.
            //
            // The event above named the floor's decision, because that is what happened to the
            // submission. The result names validation, because that is what the reporter can act
            // on — and because the widget marks a control invalid on that reason alone.
            $failure = $this->validate($input);

            return $failure instanceof ValidationFailure
                ? SubmissionResult::rejected(RejectionReason::Validation, $failure)
                : SubmissionResult::silentlyRejected($decision->reason);
        }

        // A screenshot only where screenshots are on. The widget stores none while
        // `screenshot.strategy` is `off`, and a frontend of the host's own that passes a path
        // anyway has it dropped here, so the setting holds whatever submits. The file stays where
        // the caller put it, for the orphan sweep: a path handed in is not one to delete.
        $screenshotPath = $this->config->get('visual-feedback.screenshot.strategy') === 'off' ? null : $input->screenshotPath;

        // 2. Build the report with its stable UUID. The screenshot (if captured) is the
        // first attachment path; user uploads follow.
        $attachments = $screenshotPath !== null
            ? [$screenshotPath, ...$input->attachmentPaths]
            : $input->attachmentPaths;

        // A field the host switched off carries nothing into the report, whatever the caller
        // passed for it. The widget never sends one, and validate() passes such a field over, so
        // without this a frontend of the host's own could deliver a value the form never asked
        // for, unchecked and uncapped.
        $report = Report::forSubmission(
            category: $input->category,
            subject: $this->modeOf($input, 'subject') === Settings::FIELD_OFF ? null : $this->trimmed($input->subject),
            message: $this->trimmed($input->message),
            reporter: $this->withoutSwitchedOffFields($input),
            context: $input->context,
            attachments: $attachments,
            metadata: $input->metadata,
            mode: $input->mode,
            submittedAt: Carbon::now()->toImmutable(),
            recipient: $input->recipient,
        );

        // 3. ReportSubmitting — a synchronous listener may cancel.
        $submitting = new ReportSubmitting($report);
        $this->events->dispatch($submitting);

        if ($submitting->isRejected()) {
            $this->events->dispatch(new ReportRejected(RejectionReason::ListenerRejected, $submitting->rejectionReason()));

            return SubmissionResult::rejected(RejectionReason::ListenerRejected);
        }

        // 4. Validate the submitted data.
        $failure = $this->validate($input);

        if ($failure instanceof ValidationFailure) {
            $this->events->dispatch(new ReportRejected(RejectionReason::Validation, $failure->message));

            return SubmissionResult::rejected(RejectionReason::Validation, $failure);
        }

        // 4b. Validate the user's attachments on the caps + the server-sniffed MIME allowlist.
        $attachmentErrors = $this->attachments->validate($input->attachmentPaths);

        if ($attachmentErrors !== []) {
            $this->events->dispatch(new ReportRejected(RejectionReason::Validation, $attachmentErrors[0]));

            return SubmissionResult::rejected(
                RejectionReason::Validation,
                new ValidationFailure('attachments', $attachmentErrors[0]),
            );
        }

        // 4c. Validate the screenshot through the same kind of caps — a screenshot on its
        // own path bypasses attachment validation entirely. A valid, present screenshot fires
        // ScreenshotAttached with the report UUID + its stored path.
        $screenshotErrors = $this->screenshots->validate($screenshotPath);

        if ($screenshotErrors !== []) {
            $this->events->dispatch(new ReportRejected(RejectionReason::Validation, $screenshotErrors[0]));

            return SubmissionResult::rejected(
                RejectionReason::Validation,
                new ValidationFailure('screenshot', $screenshotErrors[0]),
            );
        }

        if ($screenshotPath !== null) {
            $this->events->dispatch(new ScreenshotAttached($report->id, $screenshotPath));
        }

        // 5. Dispatch to the enabled + available delivery channels (each queues its own job).
        $handedTo = $this->channels->dispatch($report);

        // 6. Accepted — and the widget is told whether anything actually took it. The event fires
        // either way: a host that delivers from a listener still gets its report, and the count
        // is about what the package arranged, not about what the host does afterwards.
        $this->events->dispatch(new ReportSubmitted($report));

        return SubmissionResult::accepted($report, handedToAChannel: $handedTo > 0);
    }

    /**
     * Validation messages owned by this package, so a rejection reads the same in every locale it
     * ships — independent of what the host app has under `validation.*`.
     *
     * Only the rules used above. A rule added to $rules without a message here falls back to the
     * host's lines, which is the situation this exists to avoid.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'required' => (string) trans('visual-feedback::messages.validation.required'),
            'in' => (string) trans('visual-feedback::messages.validation.in'),
            'email' => (string) trans('visual-feedback::messages.validation.email'),
            // The two character rules of HeaderSafeEmail refuse an address, so they say what
            // `email` says.
            'guest_email.not_regex' => (string) trans('visual-feedback::messages.validation.email'),
            'guest_email.regex' => (string) trans('visual-feedback::messages.validation.email'),
            // `max`, not `max.string`. Laravel looks an inline message up under
            // "{$attribute}.{$rule}", "{$rule}" and "{$attribute}" — nothing else — so
            // `max.string` only ever matches an attribute literally called `max`
            // validated by a `string` rule. It matched nothing here, and the fallback is
            // silent: Laravel serves its own validation.max.string line, so every
            // over-long field showed "The Subject field must not be greater than 20
            // characters" while this package shipped a translated sentence in seven
            // locales that no reporter ever saw. (The dotted form is what a size rule
            // wants in a translation file, where the type is a nested key; an inline
            // message array is indexed by the rule alone.)
            'max' => (string) trans('visual-feedback::messages.validation.max'),
            'string' => (string) trans('visual-feedback::messages.validation.string'),
        ];
    }

    /**
     * `:attribute` in those messages, named the way the reporter sees the field — the widget's own
     * labels, not the domain keys. Without this the message says "guest_email". Each label loses
     * its "(optional)" note, see FieldLabel.
     *
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return array_map(FieldLabel::withoutOptionalMarker(...), [
            'category' => (string) trans('visual-feedback::messages.widget.category_label'),
            'subject' => (string) trans('visual-feedback::messages.widget.subject_label'),
            'message' => (string) trans('visual-feedback::messages.widget.message_label'),
            'guest_name' => (string) trans('visual-feedback::messages.widget.name_label'),
            'guest_email' => (string) trans('visual-feedback::messages.widget.email_label'),
            'guest_phone' => (string) trans('visual-feedback::messages.widget.phone_label'),
        ]);
    }

    /** The first validation failure, its field and message, or null when the submission is valid. */
    private function validate(SubmissionInput $input): ?ValidationFailure
    {
        // What the call site offered, falling back to the configured list. Checked against the
        // configured list alone, a widget mounted with its own `categories` would render options
        // this rule then rejects — the picker and the validator would disagree, and the reporter
        // would lose.
        //
        // An int is kept and cast, not dropped, and the difference is the whole submission. An
        // `is_string(...)` filter is right for config data — a host can put anything in there —
        // and catastrophic for the offered list: PHP converts a numeric-string array key to an
        // int on write, so a configured `['101', '102']` reaches here as `[101, 102]`, every entry
        // would be discarded, `Rule::in([])` renders as a bare `in:`, and every category would be
        // invalid. The reporter would be told their choice is wrong with nothing they can do
        // about it, on a shape this package documents as supported.
        //
        // Dropping is still right for a value no category key can be — an array, an object, a
        // bool, a null. Those cannot round-trip through a form field, so admitting them would
        // widen the allowlist rather than repair it.
        /** @var list<string> $categories */
        $categories = array_values(array_map(
            strval(...),
            array_filter(
                $input->allowedCategories !== []
                    ? $input->allowedCategories
                    : (is_array($configured = $this->config->get('visual-feedback.categories')) ? $configured : []),
                static fn (mixed $key): bool => is_string($key) || is_int($key),
            )
        ));

        $subjectMax = $this->configInt('visual-feedback.fields.subject.max_length', 150);
        $messageMax = $this->configInt('visual-feedback.fields.message.max_length', 50_000);

        $data = ['category' => $input->category];
        $rules = ['category' => ['required', 'string', Rule::in($categories)]];

        // A field whose mode is `off` is not validated, and handle() does not carry its value
        // (see withoutSwitchedOffFields()): the widget already drops it, and dropping it there too
        // means a caller that reaches this pipeline directly cannot smuggle in a value for a field
        // the host switched off. Validating it instead would be the wrong shape — `nullable`
        // accepts the smuggled value, `required` rejects a form that never showed the input.
        //
        // The order of the rules is the order the first failure is named in, so the subject keeps
        // its place between the category and the message.
        //
        // The rules judge the trimmed text, the same text the report carries (see trimmed()).
        if ($this->modeOf($input, 'subject') !== Settings::FIELD_OFF) {
            $data['subject'] = $this->trimmed($input->subject);
            $rules['subject'] = [$this->requiredness($input, 'subject'), 'string', "max:{$subjectMax}"];
        }

        $data['message'] = $this->trimmed($input->message);
        $rules['message'] = ['required', 'string', "max:{$messageMax}"];

        // Guest identity fields, each governed by its own `fields.<f>.mode`, by the same rule. An
        // authenticated reporter's identity comes from the guard, so none of this applies to them.
        if ($input->reporter->isGuest) {
            $lengths = [
                'name' => $this->configInt('visual-feedback.fields.name.max_length', 150),
                'email' => $this->configInt('visual-feedback.fields.email.max_length', 254),
                'phone' => $this->configInt('visual-feedback.fields.phone.max_length', 32),
            ];

            $given = [
                'name' => $this->trimmed($input->reporter->name),
                'email' => $this->trimmed($input->reporter->email),
                'phone' => $this->trimmed($input->reporter->phone),
            ];

            foreach ($lengths as $field => $max) {
                if ($this->modeOf($input, $field) === Settings::FIELD_OFF) {
                    continue;
                }

                // The address becomes the report mail's Reply-To header, so it is held to the
                // rules for an address in a header, see HeaderSafeEmail.
                $data["guest_{$field}"] = $given[$field];
                $rules["guest_{$field}"] = array_values(array_filter([
                    $this->requiredness($input, $field),
                    'string',
                    ...($field === 'email' ? HeaderSafeEmail::RULES : []),
                    "max:{$max}",
                ]));
            }
        }

        // Messages and attribute names come from this package, in all seven locales. Leaving them
        // to the host's `validation.*` lines would show whatever that app happens to have — often
        // English only, and with `guest_email` as the field name.
        $validator = $this->validator->make($data, $rules, $this->messages(), $this->attributeNames());

        if (! $validator->fails()) {
            return null;
        }

        /** @var array<string, list<string>> $errors */
        $errors = $validator->errors()->toArray();
        $field = (string) array_key_first($errors);

        return new ValidationFailure($field, (string) ($errors[$field][0] ?? ''));
    }

    /**
     * The reporter as submitted, less the guest fields the host switched off.
     *
     * A signed-in reporter's identity comes from the guard rather than from the form, so it is
     * returned as it is.
     */
    private function withoutSwitchedOffFields(SubmissionInput $input): Reporter
    {
        $reporter = $input->reporter;

        if (! $reporter->isGuest) {
            return $reporter;
        }

        $kept = fn (string $field, ?string $value): ?string => $this->modeOf($input, $field) === Settings::FIELD_OFF ? null : $this->trimmed($value);

        return Reporter::guest(
            $kept('name', $reporter->name),
            $kept('email', $reporter->email),
            $kept('phone', $reporter->phone),
        );
    }

    /**
     * A free-text field as Laravel's TrimStrings middleware hands a form field over, without NUL
     * bytes.
     *
     * Livewire switches that middleware off for its own requests, and the `required` rule trims
     * with PHP's trim(), which knows only ASCII whitespace. A message of nothing but U+3000, the
     * full-width space an IME types for each press of the space bar, or of U+00A0 or U+200B, passed
     * as written, and an empty report was mailed and stored. Str::trim() removes Unicode whitespace
     * and the zero-width marks as well. Every adapter reaches this class, so the text the rules
     * judge and the text the report carries are trimmed here.
     *
     * A NUL byte is removed before the trim. PostgreSQL cuts a text value at the first one without
     * an error, so the database channel would store a message only up to that byte while mail and
     * webhook carry all of it. The byte carries nothing a reader of the report needs.
     *
     * @return ($value is null ? null : string)
     */
    private function trimmed(?string $value): ?string
    {
        return $value === null ? null : Str::trim(str_replace("\0", '', $value));
    }

    /** `required` or `nullable` for one field, from the single `fields.<f>.mode` vocabulary. */
    private function requiredness(SubmissionInput $input, string $field): string
    {
        return $this->modeOf($input, $field) === Settings::FIELD_REQUIRED ? 'required' : 'nullable';
    }

    /**
     * The mode of one field for this submission: the one the call site resolved, else the
     * configured one.
     *
     * The call site's mode wins because it is the form the reporter was shown. Judging that form
     * by the configuration instead refused a report over a field the page had switched off, and
     * waved through an empty one the page had marked as required.
     */
    private function modeOf(SubmissionInput $input, string $field): string
    {
        $mode = $input->fieldModes[$field] ?? null;

        return is_string($mode) && in_array($mode, Settings::FIELD_MODES, true)
            ? $mode
            : $this->settings->fieldMode($field);
    }

    private function configInt(string $key, int $default): int
    {
        $value = $this->config->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
