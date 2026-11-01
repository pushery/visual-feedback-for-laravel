{{--
    The WireKit twin of the widget for a page without a session and without Livewire — `<x-visual-feedback::standalone />`
    while the WireKit tree is served.

    The same form as the plain view, with the same ids, names and hooks, so the widget's script reads it the same way: it
    opens the dialog, fetches a form token when the form opens, and sends the fields by their `name` attributes. The
    controls are the kit's token-styled components. The dialog stays the native element the plain view opens, which
    needs no Livewire to open or close.

    A page that serves this tree loads WireKit's script bundle that brings Alpine's CSP build with it, `csp` under
    `wirekit.scripts.bundle`, through `@wirekitScripts`, and no Alpine of its own.

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
        {{-- The kit's close icon, never a typed character, and the name as visually hidden text. No tooltip, as on the
             kit's own modal: its panel teleports out of the dialog, and showModal() lifts the dialog above everything
             outside it. --}}
        <x-wirekit::button type="button" class="visual-feedback-close" intent="neutral" surface="soft" size="sm" icon-only
            x-on:click="$refs.dialog.close()">
            <x-slot:iconLeft><x-wirekit::icon name="close" size="sm" aria-hidden="true" /></x-slot:iconLeft>
            {{ __('visual-feedback::messages.widget.close') }}
        </x-wirekit::button>

        {{-- Always present and empty at first, so a screen reader announces the success without a focus jump. The kit's
             alert carries `role="presentation"`: it would set `status` itself, and a status region inside this one can be
             announced twice. --}}
        <div role="status" aria-live="polite">
            <template x-if="vfSent">
                <div>
                    <x-wirekit::alert intent="success" role="presentation" class="visual-feedback-success">{{ __('visual-feedback::messages.widget.success') }}</x-wirekit::alert>
                    <x-wirekit::button type="button" class="visual-feedback-report-another" x-ref="reportAnother" x-on:click="vfResetAndFocus()">
                        {{ __('visual-feedback::messages.widget.report_another') }}
                    </x-wirekit::button>
                    <x-wirekit::button type="button" class="visual-feedback-done" intent="neutral" surface="soft" x-on:click="vfDone()">
                        {{ __('visual-feedback::messages.widget.done') }}
                    </x-wirekit::button>
                </div>
            </template>
        </div>

        <form x-ref="form" novalidate x-show="! vfSent" x-on:submit.prevent="vfSubmit($el)">
            <x-wirekit::heading level="2" id="visual-feedback-heading" class="visual-feedback-heading" tabindex="-1" autofocus>
                {{ __('visual-feedback::messages.widget.heading') }}
            </x-wirekit::heading>

            @if ($vfNotice !== null)
                <x-wirekit::text id="visual-feedback-notice" class="visual-feedback-notice">{{ $vfNotice }}</x-wirekit::text>
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

            {{-- Every control carries its `name` beside its id. The kit names a control after its id when it is given no
                 name, and the script sends the form by name. The invalid state and its description are bound with
                 `x-bind:` rather than a colon, which Blade would read as PHP: the script decides them in the browser. --}}
            @foreach (['name' => ['text', 'name'], 'email' => ['email', 'email'], 'phone' => ['tel', 'tel']] as $vfField => [$vfType, $vfAutocomplete])
                @if ($vfSettings->fieldIsShown($vfField))
                    <x-wirekit::input
                        id="visual-feedback-{{ $vfField }}" name="{{ $vfField }}" type="{{ $vfType }}" autocomplete="{{ $vfAutocomplete }}"
                        :label="$vfFieldLabels[$vfField]"
                        :required="in_array($vfField, $vfRequired, true)"
                        x-bind:aria-invalid="vfInvalid('{{ $vfField }}')" x-bind:aria-describedby="vfDescribedBy('{{ $vfField }}')" />
                @endif
            @endforeach

            <x-wirekit::select
                id="visual-feedback-category" name="category"
                :label="__('visual-feedback::messages.widget.category_label')"
                required
                :options="$vfCategories"
                x-bind:aria-invalid="vfInvalid('category')" x-bind:aria-describedby="vfDescribedBy('category')" />

            @if ($vfSettings->fieldIsShown('subject'))
                <x-wirekit::input
                    id="visual-feedback-subject" name="subject" type="text"
                    :label="$vfFieldLabels['subject']"
                    :required="in_array('subject', $vfRequired, true)"
                    x-bind:aria-invalid="vfInvalid('subject')" x-bind:aria-describedby="vfDescribedBy('subject')" />
            @endif

            <div x-data="visualFeedbackCounter({ max: {{ is_numeric($vfMax = config('visual-feedback.fields.message.max_length')) ? (int) $vfMax : 50000 }}, locale: {{ \Pushery\WireKit\Support\AlpinePayload::from(str_replace('_', '-', app()->getLocale())) }} })">
                <x-wirekit::textarea
                    id="visual-feedback-message" name="message"
                    :label="__('visual-feedback::messages.widget.message_label')"
                    required
                    x-on:input="measure($event.target.value)"
                    :placeholder="__('visual-feedback::messages.widget.message_placeholder')"
                    x-bind:aria-invalid="vfInvalid('message')" x-bind:aria-describedby="vfDescribedBy('message')" />
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
                    <x-wirekit::button type="button" class="visual-feedback-capture" surface="soft"
                        x-ref="idle" x-show="status === 'idle'" x-on:click="capture()">
                        {{ __('visual-feedback::messages.widget.capture_screenshot') }}
                    </x-wirekit::button>
                    <p class="visual-feedback-native-hint" x-cloak x-show="status === 'idle' && nativeAvailable()">
                        {{ __('visual-feedback::messages.widget.screenshot_native_hint') }}
                    </p>
                    {{-- A `div`, because the kit draws the attached line as a `div`, which a `p` cannot hold. --}}
                    <div class="visual-feedback-capture-status" aria-live="polite">
                        <span x-show="status === 'capturing'">{{ __('visual-feedback::messages.widget.screenshot_capturing') }}</span>
                        <span x-show="status === 'uploading'">{{ __('visual-feedback::messages.widget.screenshot_uploading') }}</span>
                        <span x-show="editorFailed">{{ __('visual-feedback::messages.widget.editor_failed') }}</span>
                        <x-wirekit::alert intent="success" role="presentation" class="visual-feedback-success"
                            x-show="status === 'attached'">{{ __('visual-feedback::messages.widget.screenshot_attached') }}</x-wirekit::alert>
                    </div>
                    <div class="visual-feedback-captured-pending" x-show="status === 'captured'">
                        <template x-if="previewUrl">
                            <img class="visual-feedback-preview" :src="previewUrl" alt="{{ __('visual-feedback::messages.widget.screenshot_preview') }}" decoding="async">
                        </template>
                        <div class="visual-feedback-preview-actions">
                            <x-wirekit::button type="button" id="visual-feedback-screenshot" intent="success" x-ref="captured" x-on:click="attach()">{{ __('visual-feedback::messages.widget.screenshot_attach') }}</x-wirekit::button>
                            @if ($vfCaptureConfig['editor'])
                                <x-wirekit::button type="button" class="visual-feedback-edit" intent="neutral" surface="soft" x-ref="edit" x-on:click="openEditor()">{{ __('visual-feedback::messages.widget.editor_open') }}</x-wirekit::button>
                            @endif
                            <x-wirekit::button type="button" intent="neutral" surface="soft" x-on:click="discard()">{{ __('visual-feedback::messages.widget.screenshot_discard') }}</x-wirekit::button>
                            <x-wirekit::button type="button" intent="neutral" surface="soft" x-on:click="retake()">{{ __('visual-feedback::messages.widget.screenshot_retake') }}</x-wirekit::button>
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
                    <x-wirekit::button type="button" class="visual-feedback-retake" intent="neutral" surface="soft" x-ref="attached"
                        x-show="status === 'attached'" x-on:click="retake()">
                        {{ __('visual-feedback::messages.widget.screenshot_retake') }}
                    </x-wirekit::button>
                    <div class="visual-feedback-alert" role="alert" aria-live="assertive">
                        <x-wirekit::alert intent="danger" role="presentation" class="visual-feedback-alert--shown"
                            x-show="status === 'failed'">
                            {{ __('visual-feedback::messages.widget.screenshot_failed') }}
                            <x-wirekit::button type="button" intent="neutral" surface="soft" x-ref="failed" x-on:click="retake()">{{ __('visual-feedback::messages.widget.screenshot_retake') }}</x-wirekit::button>
                        </x-wirekit::alert>
                    </div>
                </div>
            @endif

            {{-- No file field where the server takes no file: `attachments.max_files` of 0. A native field, as in the
                 plain view: the kit's dropzone hands its files to Livewire's upload, which a cached page does not have,
                 and the script sends what the field holds. --}}
            @if ($vfPolicy->maxFiles() > 0)
            <div class="visual-feedback-attachments">
                <label for="visual-feedback-files">{{ __('visual-feedback::messages.widget.attachments_label') }}</label>
                <input id="visual-feedback-files" class="visual-feedback-file-input" type="file" name="attachments[]" multiple
                    accept="{{ $vfPolicy->acceptAttribute() }}" aria-describedby="visual-feedback-files-limit"
                    :aria-invalid="vfInvalid('files')">
                <x-wirekit::text id="visual-feedback-files-limit" class="visual-feedback-hint">{{ trans_choice('visual-feedback::messages.widget.attachment_limit', $vfPolicy->maxFiles(), ['count' => $vfPolicy->maxFiles(), 'size' => $vfPolicy->maxFileSizeForHumans()]) }}</x-wirekit::text>
            </div>
            @endif

            <div class="visual-feedback-error visual-feedback-alert" :class="{ 'visual-feedback-alert--shown': vfFailureCount > 0 }"
                id="visual-feedback-error" role="alert" aria-live="assertive" x-text="vfFailure"></div>

            @if ($vfPrivacyUrl)
                {{-- The sentence is the link, as in the plain view, and the kit's checkbox takes it as its label slot. --}}
                <x-wirekit::checkbox
                    id="visual-feedback-privacy" name="privacy" value="1"
                    required
                    x-bind:aria-invalid="vfInvalid('privacy')" x-bind:aria-describedby="vfDescribedBy('privacy')">
                    <a href="{{ $vfPrivacyUrl }}" target="_blank" rel="noopener noreferrer">{{ $vfPrivacy->wording()?->text ?? __('visual-feedback::messages.widget.privacy_acknowledge') }}</a>
                </x-wirekit::checkbox>
            @endif

            <p class="visual-feedback-required-legend">
                <span class="visual-feedback-required-mark" aria-hidden="true">*</span>
                {{ __('visual-feedback::messages.widget.required_legend') }}
            </p>
            <x-wirekit::button type="submit" class="visual-feedback-submit" intent="primary" x-bind:disabled="vfSending">
                {{ __('visual-feedback::messages.widget.submit') }}
            </x-wirekit::button>
        </form>
    </dialog>
</div>
@endif
