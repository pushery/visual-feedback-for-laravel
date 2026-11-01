{{--
    The widget for a page without a session and without Livewire — `<x-visual-feedback::standalone />`.

    It renders the same form as the Livewire widget, from the configuration alone, so the markup is the same for every
    visitor and the page stays cacheable: no CSRF token, no session, nothing about who is looking. When the form opens,
    the widget fetches a form token from the routes StandaloneRoutes::register() adds, and it sends the report itself.
    Every reporter here is a guest.

    The layout carries `<x-visual-feedback::scripts />` as it does for the Livewire widget. The widget renders nothing
    while the package is switched off or requires a sign-in, because neither can be answered for a page that is the
    same for everybody.

    Props:
      position — the corner of the floating trigger; the configured one by default.
      notice — a sentence of the host's above the fields, such as who reads a report; rendered as text and named by
               the dialog's aria-describedby. None by default.
--}}
@props(['position' => null, 'notice' => null])
@php
    $vfSettings = app(\Pushery\VisualFeedback\Support\Settings::class);
    $vfAvailable = $vfSettings->enabled() && ! $vfSettings->requiresAuthentication();
    $vfNotice = is_string($notice) && trim($notice) !== '' ? $notice : null;
@endphp
@if ($vfAvailable)
@php
    $vfLabels = app(\Pushery\VisualFeedback\Support\CategoryLabels::class);
    $vfCategories = [];

    foreach ((array) config('visual-feedback.categories', []) as $vfKey) {
        if (is_string($vfKey)) {
            $vfCategories[$vfKey] = $vfLabels->label($vfKey);
        }
    }

    $vfRequired = array_values(array_filter(['subject', 'name', 'email', 'phone'], fn (string $field): bool => $vfSettings->fieldIsRequired($field)));
    $vfFieldLabels = [];

    foreach ([
        'subject' => __('visual-feedback::messages.widget.subject_label'),
        'name' => __('visual-feedback::messages.widget.name_label'),
        'email' => __('visual-feedback::messages.widget.email_label'),
        'phone' => __('visual-feedback::messages.widget.phone_label'),
    ] as $vfField => $vfLabel) {
        $vfLabel = (string) $vfLabel;
        $vfFieldLabels[$vfField] = in_array($vfField, $vfRequired, true) ? \Pushery\VisualFeedback\Support\FieldLabel::withoutOptionalMarker($vfLabel) : $vfLabel;
    }

    $vfPrivacy = app(\Pushery\VisualFeedback\Privacy\PrivacyNotice::class);
    $vfPrivacyUrl = $vfPrivacy->url();
    $vfPolicy = app(\Pushery\VisualFeedback\Attachments\AttachmentPolicy::class);
    $vfCaptureConfig = \Pushery\VisualFeedback\Support\ClientConfig::screenshot();
    $vfChallengeView = $vfSettings->challengeView();
    $vfEndpoints = [
        // Relative, so one cached copy of the page serves every host name it is reached by.
        'form' => route(\Pushery\VisualFeedback\Standalone\StandaloneRoutes::FORM, absolute: false),
        'reports' => route(\Pushery\VisualFeedback\Standalone\StandaloneRoutes::REPORTS, absolute: false),
        'failure' => (string) __('visual-feedback::messages.widget.error'),
    ];
@endphp
<div
    x-data="visualFeedbackStandalone()"
    data-visual-feedback-standalone="{{ json_encode($vfEndpoints, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) }}"
    x-on:visual-feedback:open.window="vfOpen()"
>
    @if (config('visual-feedback.ui.trigger') !== 'none')
        <x-visual-feedback::fab :position="$position ?? (is_string($vfPosition = config('visual-feedback.ui.position')) ? $vfPosition : 'bottom-end')" />
    @endif

    <dialog x-ref="dialog" class="visual-feedback-dialog" aria-label="{{ __('visual-feedback::messages.widget.heading') }}" @if ($vfNotice !== null) aria-describedby="visual-feedback-notice" @endif>
        <button type="button" class="visual-feedback-close" aria-label="{{ __('visual-feedback::messages.widget.close') }}" x-on:click="$refs.dialog.close()">&times;</button>

        {{-- Always present and empty at first, so a screen reader announces the success without a focus jump. --}}
        <div role="status" aria-live="polite">
            <template x-if="vfSent">
                <div>
                    <p class="visual-feedback-success"><span class="visual-feedback-success-glyph" aria-hidden="true">✓</span>{{ __('visual-feedback::messages.widget.success') }}</p>
                    <button type="button" class="visual-feedback-report-another" x-ref="reportAnother" x-on:click="vfResetAndFocus()">
                        {{ __('visual-feedback::messages.widget.report_another') }}
                    </button>
                    <button type="button" class="visual-feedback-done" x-on:click="vfDone()">
                        {{ __('visual-feedback::messages.widget.done') }}
                    </button>
                </div>
            </template>
        </div>

        <form x-ref="form" novalidate x-show="! vfSent" x-on:submit.prevent="vfSubmit($el)">
            <h2 id="visual-feedback-heading" class="visual-feedback-heading" tabindex="-1" autofocus>
                {{ __('visual-feedback::messages.widget.heading') }}
            </h2>

            @if ($vfNotice !== null)
                <p id="visual-feedback-notice" class="visual-feedback-notice">{{ $vfNotice }}</p>
            @endif

            {{-- Off-screen, hidden from assistive technology and never reachable by tab. A filled value is refused
                 silently. The inline style hides it where the stylesheet is missing; the class hides it where a
                 content security policy drops style attributes. --}}
            <div class="visual-feedback-honeypot" aria-hidden="true" style="position:absolute;width:1px;height:1px;overflow:hidden;left:-9999px;">
                <label>
                    {{ __('visual-feedback::messages.widget.honeypot_label') }}
                    <input type="text" name="feedback_reference" tabindex="-1" autocomplete="off">
                </label>
            </div>

            @if ($vfChallengeView)
                <div class="visual-feedback-challenge" style="overflow-x:auto" x-init="vfWatchChallenge($el)">
                    @includeIf($vfChallengeView)
                </div>
            @endif

            @foreach (['name' => ['text', 'name'], 'email' => ['email', 'email'], 'phone' => ['tel', 'tel']] as $vfField => [$vfType, $vfAutocomplete])
                @if ($vfSettings->fieldIsShown($vfField))
                    <label for="visual-feedback-{{ $vfField }}">
                        {{ $vfFieldLabels[$vfField] }}@if (in_array($vfField, $vfRequired, true))<span class="visual-feedback-required-mark" aria-hidden="true">*</span>@endif
                    </label>
                    <input id="visual-feedback-{{ $vfField }}" type="{{ $vfType }}" name="{{ $vfField }}" autocomplete="{{ $vfAutocomplete }}"
                        :aria-invalid="vfInvalid('{{ $vfField }}')" :aria-describedby="vfDescribedBy('{{ $vfField }}')"
                        @required(in_array($vfField, $vfRequired, true))>
                @endif
            @endforeach

            <label for="visual-feedback-category">
                {{ __('visual-feedback::messages.widget.category_label') }}<span class="visual-feedback-required-mark" aria-hidden="true">*</span>
            </label>
            <select id="visual-feedback-category" name="category" required :aria-invalid="vfInvalid('category')" :aria-describedby="vfDescribedBy('category')">
                @foreach ($vfCategories as $vfKey => $vfLabel)
                    <option value="{{ $vfKey }}">{{ $vfLabel }}</option>
                @endforeach
            </select>

            @if ($vfSettings->fieldIsShown('subject'))
                <label for="visual-feedback-subject">
                    {{ $vfFieldLabels['subject'] }}@if (in_array('subject', $vfRequired, true))<span class="visual-feedback-required-mark" aria-hidden="true">*</span>@endif
                </label>
                <input id="visual-feedback-subject" type="text" name="subject" :aria-invalid="vfInvalid('subject')" :aria-describedby="vfDescribedBy('subject')"
                    @required(in_array('subject', $vfRequired, true))>
            @endif

            <div x-data="visualFeedbackCounter({ max: {{ is_numeric($vfMax = config('visual-feedback.fields.message.max_length')) ? (int) $vfMax : 50000 }}, locale: {{ json_encode(str_replace('_', '-', app()->getLocale()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }} })">
                <label for="visual-feedback-message">
                    {{ __('visual-feedback::messages.widget.message_label') }}<span class="visual-feedback-required-mark" aria-hidden="true">*</span>
                </label>
                <textarea id="visual-feedback-message" name="message" required :aria-invalid="vfInvalid('message')" :aria-describedby="vfDescribedBy('message')"
                    x-on:input="measure($event.target.value)"
                    placeholder="{{ __('visual-feedback::messages.widget.message_placeholder') }}"></textarea>
                <span class="visual-feedback-counter" aria-hidden="true" x-text="tally()">0</span>
                <span class="visual-feedback-sr-only" aria-live="polite" x-text="announced"
                    style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;border:0;overflow:hidden;white-space:nowrap;clip-path:inset(50%);"></span>
            </div>

            @if ($vfCaptureConfig['strategy'] !== 'off')
                {{-- The same capture island as the Livewire widget. Attach hands the picture to this form instead of to
                     a server, and it travels with the report. --}}
                <div class="visual-feedback-screenshot"
                    x-data="visualFeedbackCapture()"
                    data-visual-feedback-capture="{{ json_encode($vfCaptureConfig, JSON_THROW_ON_ERROR) }}"
                    x-on:visual-feedback:standalone-reset.window="reset()">
                    <button type="button" class="visual-feedback-capture" x-ref="idle" x-show="status === 'idle'" x-on:click="capture()">
                        {{ __('visual-feedback::messages.widget.capture_screenshot') }}
                    </button>
                    <p class="visual-feedback-native-hint" x-cloak x-show="status === 'idle' && nativeAvailable()">
                        {{ __('visual-feedback::messages.widget.screenshot_native_hint') }}
                    </p>
                    <p class="visual-feedback-capture-status" aria-live="polite">
                        <span x-show="status === 'capturing'">{{ __('visual-feedback::messages.widget.screenshot_capturing') }}</span>
                        <span x-show="status === 'uploading'">{{ __('visual-feedback::messages.widget.screenshot_uploading') }}</span>
                        <span x-show="editorFailed">{{ __('visual-feedback::messages.widget.editor_failed') }}</span>
                        <span class="visual-feedback-success" x-show="status === 'attached'"><span class="visual-feedback-success-glyph" aria-hidden="true">✓</span>{{ __('visual-feedback::messages.widget.screenshot_attached') }}</span>
                    </p>
                    <div class="visual-feedback-captured-pending" x-show="status === 'captured'">
                        <template x-if="previewUrl">
                            <img class="visual-feedback-preview" :src="previewUrl" alt="{{ __('visual-feedback::messages.widget.screenshot_preview') }}" decoding="async">
                        </template>
                        <div class="visual-feedback-preview-actions">
                            <button type="button" id="visual-feedback-screenshot" x-ref="captured" x-on:click="attach()">{{ __('visual-feedback::messages.widget.screenshot_attach') }}</button>
                            @if ($vfCaptureConfig['editor'])
                                <button type="button" class="visual-feedback-edit" x-ref="edit" x-on:click="openEditor()">{{ __('visual-feedback::messages.widget.editor_open') }}</button>
                            @endif
                            <button type="button" x-on:click="discard()">{{ __('visual-feedback::messages.widget.screenshot_discard') }}</button>
                            <button type="button" x-on:click="retake()">{{ __('visual-feedback::messages.widget.screenshot_retake') }}</button>
                        </div>
                    </div>
                    @if ($vfCaptureConfig['editor'])
                        <dialog class="visual-feedback-editor" id="visual-feedback-editor" x-ref="editor"
                            aria-labelledby="visual-feedback-editor-title"
                            data-visual-feedback-editor="{{ json_encode(\Pushery\VisualFeedback\Support\EditorTexts::current(), JSON_THROW_ON_ERROR) }}">
                            <h2 class="visual-feedback-editor-title" id="visual-feedback-editor-title">{{ __('visual-feedback::messages.widget.editor_title') }}</h2>
                            <div class="visual-feedback-editor-body" data-visual-feedback-editor-body></div>
                        </dialog>
                    @endif
                    <button type="button" class="visual-feedback-retake" x-ref="attached" x-show="status === 'attached'" x-on:click="retake()">
                        {{ __('visual-feedback::messages.widget.screenshot_retake') }}
                    </button>
                    <div class="visual-feedback-alert visual-feedback-alert--shown" x-show="status === 'failed'" role="alert" aria-live="assertive">
                        {{ __('visual-feedback::messages.widget.screenshot_failed') }}
                        <button type="button" x-ref="failed" x-on:click="retake()">{{ __('visual-feedback::messages.widget.screenshot_retake') }}</button>
                    </div>
                </div>
            @endif

            {{-- No file field where the server takes no file: `attachments.max_files` of 0. --}}
            @if ($vfPolicy->maxFiles() > 0)
            <div class="visual-feedback-attachments">
                <label for="visual-feedback-files">{{ __('visual-feedback::messages.widget.attachments_label') }}</label>
                <input id="visual-feedback-files" class="visual-feedback-file-input" type="file" name="attachments[]" multiple
                    accept="{{ $vfPolicy->acceptAttribute() }}" aria-describedby="visual-feedback-files-limit"
                    :aria-invalid="vfInvalid('files')">
                <p id="visual-feedback-files-limit" class="visual-feedback-hint">{{ trans_choice('visual-feedback::messages.widget.attachment_limit', $vfPolicy->maxFiles(), ['count' => $vfPolicy->maxFiles(), 'size' => $vfPolicy->maxFileSizeForHumans()]) }}</p>
            </div>
            @endif

            <div class="visual-feedback-error visual-feedback-alert" :class="{ 'visual-feedback-alert--shown': vfFailureCount > 0 }"
                id="visual-feedback-error" role="alert" aria-live="assertive" x-text="vfFailure"></div>

            @if ($vfPrivacyUrl)
                <label class="visual-feedback-privacy" for="visual-feedback-privacy">
                    <input id="visual-feedback-privacy" type="checkbox" name="privacy" value="1" required
                        :aria-invalid="vfInvalid('privacy')" :aria-describedby="vfDescribedBy('privacy')">
                    <a href="{{ $vfPrivacyUrl }}" target="_blank" rel="noopener noreferrer">{{ $vfPrivacy->wording()?->text ?? __('visual-feedback::messages.widget.privacy_acknowledge') }}</a>
                    <span class="visual-feedback-required-mark" aria-hidden="true">*</span>
                </label>
            @endif

            <p class="visual-feedback-required-legend">
                <span class="visual-feedback-required-mark" aria-hidden="true">*</span>
                {{ __('visual-feedback::messages.widget.required_legend') }}
            </p>
            <button type="submit" class="visual-feedback-submit" :disabled="vfSending">{{ __('visual-feedback::messages.widget.submit') }}</button>
        </form>
    </dialog>
</div>
@endif
