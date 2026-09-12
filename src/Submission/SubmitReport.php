<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Submission;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Rule;
use Pushery\VisualFeedback\Abuse\ReportAttempt;
use Pushery\VisualFeedback\Attachments\AttachmentValidator;
use Pushery\VisualFeedback\Attachments\ScreenshotValidator;
use Pushery\VisualFeedback\Channels\ChannelRegistry;
use Pushery\VisualFeedback\Contracts\AbuseGate;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Events\RejectionReason;
use Pushery\VisualFeedback\Events\ReportRejected;
use Pushery\VisualFeedback\Events\ReportSubmitted;
use Pushery\VisualFeedback\Events\ReportSubmitting;
use Pushery\VisualFeedback\Events\ScreenshotAttached;
use Pushery\VisualFeedback\Support\Settings;

/**
 * The transport-agnostic submit pipeline. The Livewire component (and any future
 * frontend adapter) is a thin shell over this: it takes a SubmissionInput and returns
 * a SubmissionResult, knowing nothing about Livewire or the request. That makes the
 * whole pipeline testable without a UI and keeps the seam open for other adapters.
 *
 * Pipeline order, fixed:
 *   0. the master switch — `visual-feedback.enabled`. It runs before EVERYTHING, including the
 *      abuse floor, because a disabled package must not touch a rate limiter, a cache or a disk,
 *      and because this is the endpoint half of a promise the shipped config makes: switch it
 *      off and the widget renders nothing AND the submit path rejects everything,
 *   1. abuse floor — the always-on AbuseGate (honeypot + time trap + rate limits) runs
 *      BEFORE validation, so a failing attempt still burns a rate-limit token,
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
        // 0. Master switch. FIRST, ahead of the abuse floor: a disabled package must not burn a
        // rate-limit token, warm a cache or touch a disk, and an operator who switched it off
        // during an incident has to be able to rely on that.
        //
        // The check lives HERE rather than only in the component, because the component is
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
        // This is the half that HOLDS. The five component templates and the widget's render()
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

        // 1. Abuse floor — the always-on gate runs BEFORE validation, so a failing attempt
        // still burns a rate-limit token. A filled honeypot or a too-fast fill
        // is a SILENT decoy (nothing stored, a bot learns nothing); a rate limit is a visible
        // rejection so a real user gets feedback — and the GATE says which of the two it is, so a
        // consumer's interactive challenge can be visible too. ReportRejected fires either way for
        // observability.
        $decision = $this->abuse->check(new ReportAttempt(
            reporter: $input->reporter,
            honeypot: $input->honeypot,
            ipAddress: $input->ipAddress,
            formOpenedAt: $input->formOpenedAt,
            submittedAt: new DateTimeImmutable,
            challenge: $input->challenge,
        ));

        if ($decision->reason instanceof RejectionReason) {
            $this->events->dispatch(new ReportRejected($decision->reason, $decision->detail));

            // The GATE decides whether the reporter is told, not this line. It used to compare
            // the reason against one hardcoded case, which silently made every reason a consumer's
            // own gate could return — `ChallengeFailed` above all — render the decoy success
            // screen. A person who fails an interactive challenge would have been shown "thanks,
            // sent" for a report that was never sent.
            if ($decision->visible) {
                return SubmissionResult::rejected($decision->reason);
            }

            // A SILENT rejection answers with the success screen, which is the right answer to a
            // bot and the wrong one to the single human case that reaches it: open the widget,
            // press send with nothing typed, and read a confirmation for a report that never
            // left. Whether the trap or the required field would have refused first is a matter
            // of seconds the reporter cannot see, and the confirmation is what stops them from
            // trying again.
            //
            // Naming the empty field gives a bot nothing it does not already have: `required`
            // stands in the markup it just read, and the category allowlist is rendered as the
            // picker's own options. A submission that is COMPLETE and too fast still gets the
            // decoy, so the trap keeps every case it was built for.
            //
            // The event above named the floor's decision, because that is what happened to the
            // submission. The RESULT names validation, because that is what the reporter can act
            // on — and because the widget marks a control invalid on that reason alone.
            $failure = $this->validate($input);

            return $failure instanceof ValidationFailure
                ? SubmissionResult::rejected(RejectionReason::Validation, $failure)
                : SubmissionResult::silentlyRejected($decision->reason);
        }

        // 2. Build the report with its stable UUID. The screenshot (if captured) is the
        // first attachment path; user uploads follow.
        $attachments = $input->screenshotPath !== null
            ? [$input->screenshotPath, ...$input->attachmentPaths]
            : $input->attachmentPaths;

        $report = Report::forSubmission(
            category: $input->category,
            subject: $input->subject,
            message: $input->message,
            reporter: $input->reporter,
            context: $input->context,
            attachments: $attachments,
            metadata: $input->metadata,
            mode: $input->mode,
            submittedAt: new DateTimeImmutable,
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

        // 4c. Validate the screenshot through the SAME kind of caps — a screenshot on its
        // own path bypasses attachment validation entirely. A valid, present screenshot fires
        // ScreenshotAttached with the report UUID + its stored path.
        $screenshotErrors = $this->screenshots->validate($input->screenshotPath);

        if ($screenshotErrors !== []) {
            $this->events->dispatch(new ReportRejected(RejectionReason::Validation, $screenshotErrors[0]));

            return SubmissionResult::rejected(
                RejectionReason::Validation,
                new ValidationFailure('screenshot', $screenshotErrors[0]),
            );
        }

        if ($input->screenshotPath !== null) {
            $this->events->dispatch(new ScreenshotAttached($report->id, $input->screenshotPath));
        }

        // 5. Dispatch to the enabled + available delivery channels (each queues its own job).
        $handedTo = $this->channels->dispatch($report);

        // 6. Accepted — and the widget is told whether anything actually took it. The event fires
        // either way: a host that delivers from a listener still gets its report, and the count
        // is about what the PACKAGE arranged, not about what the host does afterwards.
        $this->events->dispatch(new ReportSubmitted($report));

        return SubmissionResult::accepted($report, handedToAChannel: $handedTo > 0);
    }

    /** The first validation error message, or null when the submission is valid. */

    /**
     * Validation messages owned by THIS package, so a rejection reads the same in every locale it
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
            // `max`, NOT `max.string`. Laravel looks an inline message up under
            // "{$attribute}.{$rule}", "{$rule}" and "{$attribute}" — nothing else — so
            // `max.string` only ever matches an attribute literally called `max`
            // validated by a `string` rule. It matched nothing here, and the fallback is
            // silent: Laravel serves its OWN validation.max.string line, so every
            // over-long field showed "The Subject field must not be greater than 20
            // characters" while this package shipped a translated sentence in seven
            // locales that no reporter ever saw. (The dotted form is what a size rule
            // wants in a translation FILE, where the type is a nested key; an inline
            // message array is indexed by the rule alone.)
            'max' => (string) trans('visual-feedback::messages.validation.max'),
            'string' => (string) trans('visual-feedback::messages.validation.string'),
        ];
    }

    /**
     * `:attribute` in those messages, named the way the reporter sees the field — the widget's own
     * labels, not the domain keys. Without this the message says "guest_email".
     *
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return array_map($this->withoutOptionalMarker(...), [
            'category' => (string) trans('visual-feedback::messages.widget.category_label'),
            'subject' => (string) trans('visual-feedback::messages.widget.subject_label'),
            'message' => (string) trans('visual-feedback::messages.widget.message_label'),
            'guest_name' => (string) trans('visual-feedback::messages.widget.name_label'),
            'guest_email' => (string) trans('visual-feedback::messages.widget.email_label'),
            'guest_phone' => (string) trans('visual-feedback::messages.widget.phone_label'),
        ]);
    }

    /**
     * A label without the trailing "(optional)" note, for use as a validation attribute name.
     *
     * A LABEL and an ATTRIBUTE NAME are two different sentences, and reusing one as the other is
     * fine right up to the moment a label carries a parenthetical. "Your phone (optional)" reads
     * correctly beside its input and wrongly inside "… may not be longer than 20 characters" --
     * the message is already about one field, so restating that it is optional there says nothing
     * and reads like a mistake.
     *
     * This was latent before the subject label gained its note: `phone_label` has carried one for
     * longer, and nothing showed it because no rule on that field produces a message in the
     * tested paths. Stripping it here fixes both rather than the one that surfaced.
     *
     * Matched on a trailing parenthetical only, so a label whose NAME contains brackets keeps
     * them, and every locale is covered without listing seven translations of the word -- the
     * marker is a shape, not a vocabulary.
     */
    private function withoutOptionalMarker(string $label): string
    {
        return trim((string) preg_replace('/\s*\([^()]*\)\s*$/u', '', $label));
    }

    private function validate(SubmissionInput $input): ?ValidationFailure
    {
        // What the call site OFFERED, falling back to the configured list. A widget mounted
        // with its own `categories` used to render options this rule then rejected — the picker
        // and the validator disagreed, and the reporter lost.
        //
        // An INT IS KEPT AND CAST, NOT DROPPED, and the difference is the whole submission.
        // This filter used to be `is_string(...)`, which is right for config data — a host can
        // put anything in there — and catastrophic for the offered list: PHP converts a numeric
        // -string array key to an int on write, so a configured `['101', '102']` reaches here as
        // `[101, 102]`, every entry is discarded, `Rule::in([])` renders as a bare `in:`, and
        // EVERY category is invalid. The reporter is told their choice is wrong with nothing they
        // can do about it, on a shape this package documents as supported.
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

        $data = [
            'category' => $input->category,
            'subject' => $input->subject,
            'message' => $input->message,
        ];

        $rules = [
            'category' => ['required', 'string', Rule::in($categories)],
            'subject' => [$this->requiredness('subject'), 'string', "max:{$subjectMax}"],
            'message' => ['required', 'string', "max:{$messageMax}"],
        ];

        // Guest identity fields, each governed by its own `fields.<f>.mode`. An authenticated
        // reporter's identity comes from the guard, so none of this applies to them.
        //
        // A field whose mode is `off` is not validated AND its value is not carried: the widget
        // already drops it, and doing it here too means a caller that reaches this pipeline
        // directly cannot smuggle in a value for a field the host switched off. Validating it
        // instead would be the wrong shape — `nullable` accepts the smuggled value, `required`
        // rejects a form that never showed the input.
        if ($input->reporter->isGuest) {
            $lengths = [
                'name' => $this->configInt('visual-feedback.fields.name.max_length', 150),
                'email' => $this->configInt('visual-feedback.fields.email.max_length', 254),
                'phone' => $this->configInt('visual-feedback.fields.phone.max_length', 32),
            ];

            $given = [
                'name' => $input->reporter->name,
                'email' => $input->reporter->email,
                'phone' => $input->reporter->phone,
            ];

            foreach ($lengths as $field => $max) {
                if (! $this->settings->fieldIsShown($field)) {
                    continue;
                }

                $data["guest_{$field}"] = $given[$field];
                $rules["guest_{$field}"] = array_values(array_filter([
                    $this->requiredness($field),
                    'string',
                    $field === 'email' ? 'email' : null,
                    "max:{$max}",
                ]));
            }
        }

        // Messages and attribute names come from THIS package, in all seven locales. Leaving them
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

    /** `required` or `nullable` for one field, from the single `fields.<f>.mode` vocabulary. */
    private function requiredness(string $field): string
    {
        return $this->settings->fieldIsRequired($field) ? 'required' : 'nullable';
    }

    private function configInt(string $key, int $default): int
    {
        $value = $this->config->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
