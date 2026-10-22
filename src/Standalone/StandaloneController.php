<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Standalone;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Pushery\VisualFeedback\Abuse\BuiltinAbuseGate;
use Pushery\VisualFeedback\Abuse\ReportAttempt;
use Pushery\VisualFeedback\Attachments\AttachmentPolicy;
use Pushery\VisualFeedback\Attachments\EmptyDirectoryPruner;
use Pushery\VisualFeedback\Attachments\FilenameSanitizer;
use Pushery\VisualFeedback\Context\ContextRegistry;
use Pushery\VisualFeedback\Contracts\ResolvesReporter;
use Pushery\VisualFeedback\Events\RejectionReason;
use Pushery\VisualFeedback\Events\ReportRejected;
use Pushery\VisualFeedback\Metadata\MetadataSanitizer;
use Pushery\VisualFeedback\Privacy\PrivacyNotice;
use Pushery\VisualFeedback\Submission\SubmissionInput;
use Pushery\VisualFeedback\Submission\SubmitReport;
use Pushery\VisualFeedback\Submission\ValidationFailure;
use Pushery\VisualFeedback\Support\Settings;
use Pushery\VisualFeedback\Support\WidgetAvailability;
use Throwable;

/**
 * The standalone widget's two endpoints: a form token when the form opens, and the report when it is sent.
 *
 * A second adapter in front of SubmitReport, beside the Livewire widget, asking the same questions in the same order
 * (see ReportWidget::submit()). The reporter is a guest, because nothing here knows a session, and the form is the
 * configured one, because a page served from a shared cache carries no per-instance props. The answer is JSON that
 * names the control and the message the Livewire widget would show. A decoy success reads like a success.
 */
final readonly class StandaloneController
{
    /** The pipeline's validation keys mapped to the controls of the standalone form. */
    private const array FIELD_OF_KEY = [
        'category' => 'category',
        'subject' => 'subject',
        'message' => 'message',
        'guest_name' => 'name',
        'guest_email' => 'email',
        'guest_phone' => 'phone',
        'attachments' => 'files',
        'screenshot' => 'screenshot',
    ];

    /**
     * A token for a form opened now, and how many seconds the time trap wants before a send. Never cached: each
     * opening is its own moment. The widget waits out those seconds before it sends, because a report that arrives
     * sooner is refused silently and would be lost behind a success screen.
     */
    public function form(StandaloneToken $tokens, Settings $settings): JsonResponse
    {
        return new JsonResponse(
            ['token' => $tokens->issue(Carbon::now()->getTimestamp()), 'ready_in' => $settings->minFillSeconds()],
            200,
            ['Cache-Control' => 'no-store, private'],
        );
    }

    public function store(Request $request, SubmitReport $submit, ResolvesReporter $reporters, Settings $settings): JsonResponse
    {
        // The token stands where the Livewire widget's seal and its session stamp stand. Without a valid one the form
        // is answered as expired, before anything is stored: reopening it is all the reporter has to do.
        $openedAt = app(StandaloneToken::class)->openedAt($this->text($request, 'token'), Carbon::now()->getTimestamp());

        if ($openedAt === null) {
            return $this->failed('message', (string) trans('visual-feedback::messages.validation.form_expired'), expired: true);
        }

        $enabled = $settings->enabled();

        // A field the configuration switched off contributes nothing, whatever the request carries.
        $reporter = $reporters->resolve(
            $settings->fieldIsShown('name') ? $this->text($request, 'name') : null,
            $settings->fieldIsShown('email') ? $this->text($request, 'email') : null,
            $settings->fieldIsShown('phone') ? $this->text($request, 'phone') : null,
        );

        if ($enabled && $reporter->isGuest && ! $request->boolean('privacy') && app(PrivacyNotice::class)->required()) {
            return $this->failed('privacy', (string) trans('visual-feedback::messages.validation.privacy_required'), invalid: true);
        }

        $screenshotOffered = config('visual-feedback.screenshot.strategy') !== 'off';

        // A capture still waiting in the preview stops the submit, as in the Livewire widget: the report would
        // otherwise go without the picture the reporter took because words were not enough.
        if ($enabled && $screenshotOffered && $request->boolean('screenshot_pending')) {
            return $this->failed('screenshot', (string) trans('visual-feedback::messages.validation.screenshot_not_attached'));
        }

        $screenshot = $screenshotOffered ? $request->file('screenshot') : null;
        $screenshot = $screenshot instanceof UploadedFile ? $screenshot : null;
        $attachments = $this->uploads($request->file('attachments'));

        $metadata = $this->metadata($request, $screenshot instanceof UploadedFile, $enabled && $reporter->isGuest);
        $honeypot = $this->text($request, 'feedback_reference');
        $challenge = $this->challenge($request);

        // The free half of the abuse floor, the switches and the spent limit, asked before anything is written, for
        // the reason ReportWidget::submit() gives: a refused attempt stores nothing. They decide nothing here;
        // handle() still runs and reaches the same verdict, with its event and its message.
        $attempt = new ReportAttempt(
            reporter: $reporter,
            honeypot: $honeypot,
            ipAddress: $request->ip(),
            formOpenedAt: $openedAt,
            submittedAt: Carbon::now()->toImmutable(),
            challenge: $challenge,
        );
        $floor = app(BuiltinAbuseGate::class);
        $storeIsWorthIt = ! $floor->silentFloor($attempt)->rejected()
            && app(WidgetAvailability::class)->forThisRequest()
            && ! $floor->limitAlreadySpent($attempt);

        $policy = app(AttachmentPolicy::class);
        $refusal = $storeIsWorthIt ? $this->perimeterRefusal($screenshot, $attachments, $policy) : null;

        if ($refusal !== null) {
            event(new ReportRejected(RejectionReason::Validation, $refusal[1]));

            return $this->failed($refusal[0], $refusal[1], invalid: true);
        }

        $attachmentPaths = $storeIsWorthIt ? $this->storeAttachments($attachments, $policy) : [];
        $screenshotPath = $storeIsWorthIt && $screenshot instanceof UploadedFile ? $this->storeScreenshot($screenshot, $policy) : null;

        $result = $submit->handle(new SubmissionInput(
            category: $this->text($request, 'category'),
            subject: $settings->fieldIsShown('subject') ? $this->text($request, 'subject') : null,
            message: $this->text($request, 'message'),
            reporter: $reporter,
            mode: 'modal',
            context: app(ContextRegistry::class)->collect(),
            metadata: $metadata,
            attachmentPaths: $attachmentPaths,
            screenshotPath: $screenshotPath,
            honeypot: $honeypot,
            ipAddress: $request->ip(),
            formOpenedAt: $openedAt,
            challenge: $challenge,
        ));

        // The files of a submission that produced no report go again, keyed on `accepted` rather than on the success
        // the reporter reads: a decoy success stores nothing, and its files are exactly the ones to reclaim.
        if (! $result->accepted) {
            $this->discard($screenshotPath === null ? $attachmentPaths : [$screenshotPath, ...$attachmentPaths], $policy);
        }

        if ($result->showsSuccess) {
            return new JsonResponse(['status' => 'sent']);
        }

        $failure = $result->failure;

        return $this->failed(
            $failure instanceof ValidationFailure ? self::FIELD_OF_KEY[$failure->field] ?? 'message' : 'message',
            $failure instanceof ValidationFailure ? $failure->message : (string) trans('visual-feedback::messages.widget.error'),
            invalid: $result->rejectionReason === RejectionReason::Validation,
        );
    }

    /**
     * The browser's metadata through the sanitizer, then what only the server may add: the capture stage and the edit
     * mark of an attached screenshot, and the provenance of the privacy notice a guest acknowledged.
     *
     * @return array<string, scalar|null>
     */
    private function metadata(Request $request, bool $withScreenshot, bool $noticeShown): array
    {
        $raw = json_decode($this->text($request, 'metadata'), true);
        $fields = [];

        // A JSON list decodes with integer keys, and the sanitizer reads named fields only, so those never reach it.
        foreach (is_array($raw) ? $raw : [] as $key => $value) {
            if (is_string($key)) {
                $fields[$key] = $value;
            }
        }

        $metadata = app(MetadataSanitizer::class)->sanitize($fields, $request->userAgent());

        if ($withScreenshot && in_array($stage = $this->text($request, 'screenshot_stage'), ['native', 'dom'], true)) {
            $metadata['capture_method'] = $stage;
        }

        if ($withScreenshot && $request->boolean('screenshot_edited')) {
            $metadata['screenshot_edited'] = true;
        }

        return $noticeShown && app(PrivacyNotice::class)->required()
            ? array_merge($metadata, app(PrivacyNotice::class)->wording()?->toMetadata() ?? [])
            : $metadata;
    }

    /**
     * The refusal the upload perimeter of the Livewire widget gives for these files, as the control and the message,
     * or null when they pass: the screenshot as a PNG within `screenshot.max_bytes`, the attachments within the types,
     * the count, the size of each and their total.
     *
     * @param  list<UploadedFile>  $attachments
     * @return array{0: string, 1: string}|null
     */
    private function perimeterRefusal(?UploadedFile $screenshot, array $attachments, AttachmentPolicy $policy): ?array
    {
        if ($screenshot instanceof UploadedFile) {
            $maxBytes = is_numeric($bytes = config('visual-feedback.screenshot.max_bytes')) ? (int) $bytes : 8 * 1024 * 1024;
            $check = Validator::make(['screenshot' => $screenshot], ['screenshot' => ['image', 'mimes:png', 'max:'.max(1, intdiv($maxBytes, 1024))]]);

            if ($check->fails()) {
                return ['screenshot', (string) $check->errors()->first('screenshot')];
            }
        }

        $check = Validator::make(['attachments' => $attachments], [
            'attachments' => ['array', 'max:'.$policy->maxFiles()],
            'attachments.*' => ['file', 'mimes:'.$policy->ruleExtensions(), 'max:'.$policy->maxFileKilobytes()],
        ]);

        if ($check->fails()) {
            return ['files', (string) $check->errors()->first()];
        }

        $total = 0;

        foreach ($attachments as $file) {
            $total += (int) $file->getSize();
        }

        return $total > $policy->maxTotalBytes()
            ? ['files', (string) trans('visual-feedback::messages.attachments.total_too_large', ['max' => $policy->megabytesFor($policy->maxTotalBytes())])]
            : null;
    }

    /** The screenshot on the private disk, under a random directory of its own, always named `screenshot.png`. */
    private function storeScreenshot(UploadedFile $screenshot, AttachmentPolicy $policy): ?string
    {
        $subdir = pathinfo($screenshot->hashName(), PATHINFO_FILENAME);
        $stored = $screenshot->storeAs($policy->directory().'/screenshots/'.$subdir, 'screenshot.png', ['disk' => $policy->disk()]);

        return is_string($stored) ? $stored : null;
    }

    /**
     * Each attachment on the private disk under a random directory of its own, keeping the sanitized name the reporter
     * gave it with the extension of its sniffed type, as the Livewire widget stores them.
     *
     * @param  list<UploadedFile>  $attachments
     * @return list<string>
     */
    private function storeAttachments(array $attachments, AttachmentPolicy $policy): array
    {
        $sanitizer = app(FilenameSanitizer::class);
        $paths = [];

        foreach ($attachments as $file) {
            $name = $sanitizer->sanitize($file->getClientOriginalName(), $file->hashName(), $file->guessExtension());
            $stored = $file->storeAs($policy->directory().'/'.pathinfo($file->hashName(), PATHINFO_FILENAME), $name, ['disk' => $policy->disk()]);

            if (is_string($stored)) {
                $paths[] = $stored;
            }
        }

        return $paths;
    }

    /**
     * Delete stored files a refused submission left behind, with the directories they emptied. A failure here leaves
     * them to the orphan sweep and never changes the answer the reporter gets.
     *
     * @param  list<string>  $paths
     */
    private function discard(array $paths, AttachmentPolicy $policy): void
    {
        if ($paths === []) {
            return;
        }

        try {
            $filesystem = Storage::disk($policy->disk());
            $filesystem->delete($paths);

            app(EmptyDirectoryPruner::class)->prune(
                $filesystem,
                $paths,
                is_string($root = config('visual-feedback.attachments.directory')) ? $root : 'visual-feedback',
            );
        } catch (Throwable) {
        }
    }

    /** @return list<UploadedFile> */
    private function uploads(mixed $files): array
    {
        $files = $files instanceof UploadedFile ? [$files] : (is_array($files) ? $files : []);

        return array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile));
    }

    /**
     * What a challenge widget put in the form, carried untouched to the gate that asked for it.
     *
     * @return array<string, mixed>
     */
    private function challenge(Request $request): array
    {
        $value = $request->input('challenge');
        $challenge = [];

        foreach (is_array($value) ? $value : [] as $key => $item) {
            if (is_string($key)) {
                $challenge[$key] = $item;
            }
        }

        return $challenge;
    }

    private function text(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : '';
    }

    /** A refusal the widget shows on the named control. `expired` tells it to fetch a new token for the next send. */
    private function failed(string $field, string $message, bool $invalid = false, bool $expired = false): JsonResponse
    {
        return new JsonResponse(['status' => 'failed', 'field' => $field, 'message' => $message, 'invalid' => $invalid, 'expired' => $expired], 422);
    }
}
