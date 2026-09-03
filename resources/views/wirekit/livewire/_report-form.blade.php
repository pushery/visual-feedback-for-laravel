{{--
    WireKit report form body — shared by the modal and inline surfaces of the WireKit
    report-widget view. Same fields, wire:model bindings, honeypot, aria-live regions,
    metadata capture and code-point counter as the plain tree, rendered with WireKit
    token-styled components (<x-wirekit::input/select/textarea/checkbox/button>) instead
    of raw controls. Lives under wirekit/ only — the plain tree keeps its own markup inline.
--}}
@php
    // WHICH control carries the invalid state -- decided once, so the eight call sites below
    // cannot drift apart, and null whenever the failure was not the reporter's doing.
    //
    // `$failedField` alone would be wrong here. It is a FOCUS TARGET: a listener veto, a rate
    // limit and the master switch being off all point it at the message box while the message
    // the reporter wrote is fine. Marking that control invalid would tell a screen reader the
    // text is wrong because the widget is switched off. `$failedFieldInvalid` is the server's
    // answer to the other question, decided where the rejection reason is still known.
@endphp
@php($vfInvalidField = $failedFieldInvalid ? $failedField : null)
{{-- ⚠️ The marking below is written as BOUND attributes (`:aria-invalid="… ? … : null"`),
     not as the `@if` the plain tree uses. A Blade directive inside a COMPONENT tag's
     attribute list breaks the component parser, and it breaks it quietly: the tag still
     renders, and the props after the directive are dropped. Measured here — the message
     field lost its label while the attribute itself appeared correctly, so a guard that
     only counted markings stayed green over eight broken tests. A bound attribute whose
     expression is null is omitted, which is the behavior this needs anyway. --}}
{{-- Status region: success + "report another", with focus moved to the button on success. --}}
<div role="status" aria-live="polite"
    x-effect="$wire.submitted && $nextTick(() => $refs.reportAnother && $refs.reportAnother.focus())">
    @if ($submitted)
        <p>{{ __('visual-feedback::messages.widget.success') }}</p>
        {{-- Resetting removes this button, so hand focus on to the fresh form's message field. --}}
        <x-wirekit::button x-ref="reportAnother"
            x-on:click="$wire.resetWidget().then(() => $nextTick(() => vfFocusIfLost(document.getElementById('visual-feedback-message'))))">
            {{ __('visual-feedback::messages.widget.report_another') }}
        </x-wirekit::button>
    @endif
</div>

@unless ($submitted)
    {{-- `novalidate` is the plain tree's twin, and it is what makes every rejection in this
         form the package's own. Two things would otherwise be answered by the browser, in the
         BROWSER's language rather than in the reporter's:

           - the guest email field is a native type="email" (WireKit emits `type` in both of
             input.blade.php's render branches), so a mistyped address is caught natively;
           - the message field and the privacy checkbox carry `required`, so an empty one is
             too.

         Either way there is no submit event, so no `wire:submit` and no
         `x-on:submit.capture` — the reporter never reaches the package's own seven-locale
         line, and `failedField` never points focus at the control.

         ⚠️ THIS COMMENT SAID "Nothing else in either tree carries `required`, so this is not
         about empty fields", AND IT WAS ALREADY WRONG WHEN IT WAS WRITTEN: the plain tree
         carried `required` on both controls. It stayed wrong in a more useful way afterwards,
         because this tree carried it on NEITHER — which is the defect it was hiding, not a
         reason the sentence was true. --}}
    <form wire:submit="submit" novalidate x-on:submit.capture="$wire.metadata = vfMeta()">
        {{-- In modal mode the heading lives in <x-wirekit::modal.header>, which owns the id the
             dialog's aria-labelledby points at; rendering it twice would announce it twice. The
             inline (card) surface has no header component, so it keeps its own heading. --}}
        @unless ($headingInHeader ?? false)
            <h2 id="visual-feedback-heading" tabindex="-1">{{ __('visual-feedback::messages.widget.heading') }}</h2>
        @endunless

        {{-- Honeypot: off-screen, hidden from AT, never tab-reachable. --}}
        <div class="visual-feedback-honeypot" aria-hidden="true"
            {{-- The class carries NO rule in this tree: it publishes no stylesheet of its
                 own. It is here as the hook the documentation names, so a host whose
                 policy forbids style attributes has one selector to write a rule against
                 rather than a nameless div to find. See the CSP section of the
                 integration contract. --}}
            style="position:absolute;width:1px;height:1px;overflow:hidden;left:-9999px;">
            <label>
                {{ __('visual-feedback::messages.widget.honeypot_label') }}
                <input type="text" wire:model="feedbackReference" tabindex="-1" autocomplete="off">
            </label>
        </div>

        {{-- Challenge region — see the plain tree for why wire:ignore and overflow-x are
             load-bearing. Same contract, same class name, same inline style, so a host's challenge
             view behaves identically in either tree. --}}
        @if ($challengeView)
            <div class="visual-feedback-challenge" style="overflow-x:auto" wire:ignore>
                @includeIf($challengeView)
            </div>
        @endif

        {{-- Every control the error handler below can jump to carries the id that handler
             names. WireKit derives an id from `id ?? name` and, with neither given, hands out
             a counted `input-1`/`input-2` instead — so leaving these off did not produce a
             different id, it produced a target that does not exist, and `?.focus()` swallowed
             the miss without a sound. Passing `id` also settles the rendered `name`
             (`$name = $attributes->get('name', $id)`), which is why these controls announce
             themselves as `visual-feedback-*` rather than as a counter. --}}
        @if ($showGuestFields)
            <x-wirekit::input
                id="visual-feedback-name" :aria-invalid="$vfInvalidField === 'name' ? 'true' : null" :aria-describedby="$vfInvalidField === 'name' ? 'visual-feedback-error' : null"
                :label="__('visual-feedback::messages.widget.name_label')"
                wire:model="guestName" autocomplete="name" />
            <x-wirekit::input
                id="visual-feedback-email" :aria-invalid="$vfInvalidField === 'email' ? 'true' : null" :aria-describedby="$vfInvalidField === 'email' ? 'visual-feedback-error' : null"
                type="email"
                :label="__('visual-feedback::messages.widget.email_label')"
                wire:model="guestEmail" autocomplete="email" />
            @if ($showPhone)
                <x-wirekit::input
                    id="visual-feedback-phone" :aria-invalid="$vfInvalidField === 'phone' ? 'true' : null" :aria-describedby="$vfInvalidField === 'phone' ? 'visual-feedback-error' : null"
                    type="tel"
                    :label="__('visual-feedback::messages.widget.phone_label')"
                    wire:model="guestPhone" autocomplete="tel" />
            @endif
        @endif

        {{-- Same id as the plain tree's select, so a host (or a test) addresses one control
             by one name regardless of which view tree is published. --}}
        <x-wirekit::select
            id="visual-feedback-category" :aria-invalid="$vfInvalidField === 'category' ? 'true' : null" :aria-describedby="$vfInvalidField === 'category' ? 'visual-feedback-error' : null"
            :label="__('visual-feedback::messages.widget.category_label')"
            wire:model="category" :options="$categoryOptions" />

        @if ($showSubject)
            <x-wirekit::input
                id="visual-feedback-subject" :aria-invalid="$vfInvalidField === 'subject' ? 'true' : null" :aria-describedby="$vfInvalidField === 'subject' ? 'visual-feedback-error' : null"
                :label="__('visual-feedback::messages.widget.subject_label')"
                wire:model="subject" />
        @endif

        {{-- Code-point counter, app-locale formatted (matches the plain tree + server).

             The two values are kept APART on purpose. `count` moves with the keystroke because
             the visible tally has to; `announced` lags it by 700 ms because the live region must
             not. One element carrying both is the established character-counter anti-pattern —
             it announces on every key — and it is what this tree used to render while the plain
             tree had already been fixed. The throttle is on the CONTENT, never on the
             `aria-live` attribute: a region that appears in the same tick as its first text can
             have that first announcement swallowed. --}}
        <div x-data="{
            count: 0,
            announced: '',
            settle: null,
            max: {{ $messageMax }},
            locale: @js($appLocale),
            init() {
                this.announced = this.tally();
            },
            tally() {
                const number = new Intl.NumberFormat(this.locale);

                return number.format(this.count) + ' / ' + number.format(this.max);
            },
            measure(value) {
                this.count = [...value].length;
                clearTimeout(this.settle);
                this.settle = setTimeout(() => { this.announced = this.tally(); }, 700);
            },
        }">
            <x-wirekit::textarea
                id="visual-feedback-message" :aria-invalid="$vfInvalidField === 'message' ? 'true' : null" :aria-describedby="$vfInvalidField === 'message' ? 'visual-feedback-error' : null"
                :label="__('visual-feedback::messages.widget.message_label')"
                wire:model="message"
                required
                x-on:input="measure($event.target.value)"
                :placeholder="__('visual-feedback::messages.widget.message_placeholder')" />
            <span class="visual-feedback-counter" aria-hidden="true" x-text="tally()">0</span>
            {{-- Off-screen live region. The inline style is what actually hides it: this tree
                 publishes no stylesheet of its own, so `visual-feedback-sr-only` would carry no
                 rule here and the region would render as a second VISIBLE counter. Same
                 reasoning as the honeypot above — the class stays as the selector a host with a
                 style-attribute policy can write a rule against. --}}
            <span class="visual-feedback-sr-only" aria-live="polite" x-text="announced"
                style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;border:0;overflow:hidden;white-space:nowrap;clip-path:inset(50%);"></span>
        </div>

        @if ($screenshotEnabled)
            {{-- Screenshot capture — the SAME tree-agnostic JS module (capture.js), capture
                 cascade and swappable uploader seam as the plain tree, rendered with WireKit
                 token-styled buttons. All status text comes from lang, never the JS bundle.
                 Each terminal state names its successor control via x-ref, so the keyboard
                 user is carried along instead of being dropped on <body> when the button they
                 just pressed is swapped out (see the plain tree for the detail). --}}
            <div class="visual-feedback-screenshot"
                x-init="$watch('status', (value) => vfFocusIfLost($refs[value]))"
                x-data="visualFeedbackCapture({ ...@js($screenshotCaptureConfig), upload: (file) => new Promise((resolve, reject) => $wire.upload('screenshot', file, resolve, reject)), onStage: (stage) => $wire.set('screenshotStage', stage) })">
                <x-wirekit::button type="button"
                    x-ref="idle"
                    x-show="status === 'idle'"
                    x-on:click="capture(document.documentElement)">
                    {{ __('visual-feedback::messages.widget.capture_screenshot') }}
                </x-wirekit::button>

                {{-- Native capture asks the browser to share the screen — say so before the
                     dialog, and only when native can actually run (never on iOS/dom-only). --}}
                <p class="visual-feedback-native-hint" x-cloak
                    x-show="status === 'idle' && nativeAvailable()">
                    {{ __('visual-feedback::messages.widget.screenshot_native_hint') }}
                </p>

                {{-- Progress + terminal status → aria-live (the Alpine state is the only source). --}}
                <p class="visual-feedback-capture-status" aria-live="polite">
                    <span x-show="status === 'capturing'">{{ __('visual-feedback::messages.widget.screenshot_capturing') }}</span>
                    <span x-show="status === 'uploading'">{{ __('visual-feedback::messages.widget.screenshot_uploading') }}</span>
                    {{-- The `attached` claim is the one piece of this status line that a server-side
                    rejection can falsify: `WithFileUploads::_finishUpload()` dispatches
                    `upload:finished` BEFORE it calls the `updated` hook, so the Alpine
                    promise resolves and sets `status = 'attached'` even when the perimeter
                    refused the file. Without this guard the aria-live region says
                    "screenshot attached" while the role="alert" region beside it says the
                    file is too large. The retake button below is deliberately NOT wrapped:
                    it is the recovery path, and its label is an offer rather than a claim. --}}
                    @unless ($errors->has('screenshot'))
                        <span x-show="status === 'attached'">{{ __('visual-feedback::messages.widget.screenshot_attached') }}</span>
                    @endunless
                </p>

                {{-- Preview before submit: discard (never uploaded), retake, or attach. --}}
                <div x-show="status === 'captured'">
                    <img class="visual-feedback-preview" :src="previewUrl"
                        alt="{{ __('visual-feedback::messages.widget.screenshot_preview') }}">
                    <div class="visual-feedback-preview-actions">
                        <x-wirekit::button type="button" intent="primary" x-ref="captured" x-on:click="attach()">{{ __('visual-feedback::messages.widget.screenshot_attach') }}</x-wirekit::button>
                        <x-wirekit::button type="button" x-on:click="discard()">{{ __('visual-feedback::messages.widget.screenshot_discard') }}</x-wirekit::button>
                        <x-wirekit::button type="button" x-on:click="retake(document.documentElement)">{{ __('visual-feedback::messages.widget.screenshot_retake') }}</x-wirekit::button>
                    </div>
                </div>

                <x-wirekit::button type="button" x-ref="attached" x-show="status === 'attached'"
                    x-on:click="retake(document.documentElement)">
                    {{ __('visual-feedback::messages.widget.screenshot_retake') }}
                </x-wirekit::button>

                {{-- A capture failure is VISIBLE with retry, not a silent console.warn. --}}
                <div x-show="status === 'failed'" role="alert" aria-live="assertive">
                    {{ __('visual-feedback::messages.widget.screenshot_failed') }}
                    <x-wirekit::button type="button" x-ref="failed" x-on:click="retake(document.documentElement)">{{ __('visual-feedback::messages.widget.screenshot_retake') }}</x-wirekit::button>
                </div>

                {{-- Perimeter error surfaced from the screenshot upload validation. --}}
                <div role="alert" aria-live="assertive">
                    @error('screenshot') {{ $message }} @enderror
                </div>
            </div>
        @endif

        {{-- Attachments via WireKit's own token-styled dropzone (drag-drop + preview +
             remove). `accept` is derived from the same server allowlist as validation. --}}
        {{-- `hint` is WireKit's own describedby-wired slot, so the caps are announced with the
             field rather than floating next to it. Same sentence as the plain tree — built once in
             the component, because two trees pluralizing it themselves is two things to keep in
             step. --}}
        {{-- `id` is a declared prop here, not a bag attribute, and it lands on the native
             `<input type="file" class="sr-only">` the label points at — sr-only is focusable,
             so it is a real focus target. Without it the id is `wk-upload-<random>`: NEW ON
             EVERY RENDER, so it could never have been named by anything. `name` is left off
             deliberately — the component only emits `name="…[]"` when it is given one, and the
             plain tree's file input carries none either. --}}
        <x-wirekit::file-upload
            id="visual-feedback-files" :aria-invalid="$vfInvalidField === 'files' ? 'true' : null" :aria-describedby="$vfInvalidField === 'files' ? 'visual-feedback-error' : null"
            wire:model="attachments"
            multiple
            :accept="$acceptAttribute"
            :hint="$attachmentLimit"
            {{-- WireKit's remove label is `__('Remove :name')` from ITS namespace, so without this
                 the button announced itself in WireKit's language, not the widget's — and the
                 widget ships seven locales. The prop arrived in WireKit 2.20. --}}
            :removeLabel="__('visual-feedback::messages.widget.remove_file', ['name' => ':name'])"
            :label="__('visual-feedback::messages.widget.attachments_label')" />

        {{-- Real-time perimeter errors surfaced from the upload validation. --}}
        <div role="alert" aria-live="assertive">
            @error('attachments') {{ $message }} @enderror
            @error('attachments.*') {{ $message }} @enderror
        </div>

        {{-- Error region. Two things are keyed on the failure COUNTER rather than on `failed`:
             that flag flips false→true inside one round-trip, so a repeat failure looks unchanged
             to Alpine and would move nothing.

             Focus goes to the control the failure belongs to, and the text is the reason the
             pipeline gave. Both used to be blunt: any failure showed one generic line and pointed
             at the message box, so a typo in the email sent the reporter to the text they had
             written correctly. --}}
        <div class="visual-feedback-error" id="visual-feedback-error" role="alert" aria-live="assertive"
            x-effect="$wire.failureCount && $nextTick(() => document.getElementById('visual-feedback-' + (['category','subject','message','name','email','phone','privacy','files'].includes($wire.failedField) ? $wire.failedField : 'message'))?.focus())">
            @if ($failed){{ $failedMessage ?? __('visual-feedback::messages.widget.error') }}@endif
        </div>

        @if ($showGuestFields && $privacyNoticeUrl)
            {{-- `$privacyNoticeWording` is the PUBLISHED sentence when a source supplies one, else
                 null and this package's own lang line is used. Safe to hand to the component:
                 WireKit renders the label as `{{ $label }}` (verified — checkbox.blade.php:185),
                 which matters because legal-consent never runs this field through a sanitizer. --}}
            <x-wirekit::checkbox
                id="visual-feedback-privacy" :aria-invalid="$vfInvalidField === 'privacy' ? 'true' : null" :aria-describedby="$vfInvalidField === 'privacy' ? 'visual-feedback-error' : null"
                wire:model="privacyAcknowledged"
                required
                :label="$privacyNoticeWording ?? __('visual-feedback::messages.widget.privacy_acknowledge')" />
            {{-- The anchor stays a SIBLING of the checkbox here, unlike the plain tree where it
                 sits inside the `<label>`: that is what keeps the checkbox's accessible name the
                 bare acknowledgement sentence, with no link text folded into it. So the link
                 needs a text of its own, and it deliberately does NOT repeat the sentence the
                 label already carries — a second copy of it would render twice on screen and
                 read as a link that never says where it goes. It names the destination instead. --}}
            <a href="{{ $privacyNoticeUrl }}" target="_blank" rel="noopener noreferrer">
                {{ __('visual-feedback::messages.widget.privacy_notice_link') }}
            </a>
        @endif

        <x-wirekit::button type="submit" intent="primary">
            {{ __('visual-feedback::messages.widget.submit') }}
        </x-wirekit::button>
    </form>
@endunless
