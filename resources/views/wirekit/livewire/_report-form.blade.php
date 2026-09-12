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
{{-- NOTE. The marking below is written as BOUND attributes (`:aria-invalid="… ? … : null"`),
     not as the `@if` the plain tree uses. A Blade directive inside a COMPONENT tag's
     attribute list breaks the component parser, and it breaks it quietly: the tag still
     renders, and the props after the directive are dropped. Measured here — the message
     field lost its label while the attribute itself appeared correctly, so a guard that
     only counted markings stayed green over eight broken tests. A bound attribute whose
     expression is null is omitted, which is the behavior this needs anyway. --}}
{{-- Status region: success + "report another", with focus moved to the button on success. --}}
<div role="status" aria-live="polite"
    x-effect="vfFocusReportAnother()">
    @if ($submitted)
        {{-- The kit draws this, not the package: `intent="success"` brings the tint, border,
             radius, icon and dark mode from the same tokens every other alert in a WireKit
             application uses. The glyph the package used to paint by hand is the component's
             own icon now, so there is one success mark in the tree rather than two that drift.
             IMPORTANT. `role="presentation"` is deliberate and it is the whole reason this is not a
             one-word change. The kit sets its OWN role -- `alert` for danger, `status`
             otherwise -- through `merge()`, which keeps a caller-supplied role and drops its
             own. Without that word this line would be a `status` region nested inside the
             `status` region around it, and a screen reader can announce a nested live region
             twice. The announcement stays on the OUTER element for the reason the region
             exists at all: a live region must be in the DOM BEFORE its content changes, and
             this alert only exists once `$submitted` is true. Same at all six sites, and the
             suite holds it: one live role per region. --}}
        <x-wirekit::alert intent="success" role="presentation" class="visual-feedback-success">{{ __('visual-feedback::messages.widget.success') }}</x-wirekit::alert>
        {{-- Two ways out of the success screen, and the order is the decision.

             "Send another" comes first because focus is moved to it: a reporter who just sent
             something and wants to send another thing is the one who needs a target, and the
             one who is finished reaches for the mouse or presses Escape. Tab finds "Done"
             immediately after, so neither is buried.

             The x in the modal header is a WINDOW gesture; this is a step of the flow, and
             inline there is no header at all -- that surface had no way to end the flow before
             this button existed. --}}
        <x-wirekit::button class="visual-feedback-report-another" x-ref="reportAnother"
            x-on:click="vfResetAndFocus()">
            {{ __('visual-feedback::messages.widget.report_another') }}
        </x-wirekit::button>
        <x-wirekit::button class="visual-feedback-done" intent="neutral" x-on:click="vfDoneWireKit()">
            {{ __('visual-feedback::messages.widget.done') }}
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

         THIS COMMENT SAID "Nothing else in either tree carries `required`, so this is not
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
            {{-- CORRECTION. This said the class carries NO rule in this tree because it publishes no
                 stylesheet of its own. Both halves are wrong: `visual-feedback::style` has a
                 WireKit block and `.visual-feedback-honeypot` is the FIRST rule in it.

                 The attribute is still not redundant, and here that is load-bearing rather than
                 belt-and-braces: a visible honeypot is filled in by real reporters, and a
                 honeypot hit is answered with the success screen on purpose — so their report is
                 thanked for and discarded. Two independent concealments, because either one can
                 be dropped by a policy the other survives. See the CSP section of the
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
            @if ($showName)
                <x-wirekit::input
                    id="visual-feedback-name" :error="$vfInvalidField === 'name' ? ($failedMessage ?? __('visual-feedback::messages.widget.error')) : null"
                    :label="__('visual-feedback::messages.widget.name_label')"
                    :required="in_array('name', $requiredFields, true)"
                    wire:model="guestName" autocomplete="name" />
            @endif
            @if ($showEmail)
                <x-wirekit::input
                    id="visual-feedback-email" :error="$vfInvalidField === 'email' ? ($failedMessage ?? __('visual-feedback::messages.widget.error')) : null"
                    type="email"
                    :label="__('visual-feedback::messages.widget.email_label')"
                    :required="in_array('email', $requiredFields, true)"
                    wire:model="guestEmail" autocomplete="email" />
            @endif
            @if ($showPhone)
                <x-wirekit::input
                    id="visual-feedback-phone" :error="$vfInvalidField === 'phone' ? ($failedMessage ?? __('visual-feedback::messages.widget.error')) : null"
                    type="tel"
                    :label="__('visual-feedback::messages.widget.phone_label')"
                    :required="in_array('phone', $requiredFields, true)"
                    wire:model="guestPhone" autocomplete="tel" />
            @endif
        @endif

        {{-- Same id as the plain tree's select, so a host (or a test) addresses one control
             by one name regardless of which view tree is published. --}}
        <x-wirekit::select
            id="visual-feedback-category" :error="$vfInvalidField === 'category' ? ($failedMessage ?? __('visual-feedback::messages.widget.error')) : null"
            :label="__('visual-feedback::messages.widget.category_label')"
            {{-- Required because the server says so, not because the form looks better with a
                 star: `SubmitReport` validates `category` as `required` unconditionally, so a
                 reporter who leaves it alone is rejected either way. It is NOT in
                 `$requiredFields` -- that list is the four fields an operator can switch
                 between optional and required, and this one is never optional. --}}
            required
            wire:model="category" :options="$categoryOptions" />

        @if ($showSubject)
            <x-wirekit::input
                id="visual-feedback-subject" :error="$vfInvalidField === 'subject' ? ($failedMessage ?? __('visual-feedback::messages.widget.error')) : null"
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
        {{-- A scalar object literal rather than one Blade-encoded array: `@js()` on a scalar
             renders a plain literal, but on an ARRAY it renders `JSON.parse('…')`, and `JSON` is
             an identifier Alpine's CSP evaluator cannot resolve. --}}
        <div x-data="visualFeedbackCounter({ max: {{ (int) $messageMax }}, locale: @js($appLocale) })">
            <x-wirekit::textarea
                id="visual-feedback-message" :error="$vfInvalidField === 'message' ? ($failedMessage ?? __('visual-feedback::messages.widget.error')) : null"
                :label="__('visual-feedback::messages.widget.message_label')"
                wire:model="message"
                required
                x-on:input="measure($event.target.value)"
                :placeholder="__('visual-feedback::messages.widget.message_placeholder')" />
            <span class="visual-feedback-counter" aria-hidden="true" x-text="tally()">0</span>
            {{-- Off-screen live region, concealed TWICE and deliberately.

                 CORRECTION. This said "this tree publishes no stylesheet of its own", and that has not
                 been true for some time: `visual-feedback::style` carries a WireKit block, and
                 `.visual-feedback-sr-only` has a rule in it. The sentence mattered, because it
                 is the reason nobody added the missing rules to that block for so long — a
                 reader who believes there is no stylesheet does not look for one.

                 The inline style stays regardless, and the real reason is the inverse of the old
                 one: the rule is the primary concealment, and the attribute is what survives a
                 host that has not included the stylesheet at all. Without both, this region
                 renders as a second VISIBLE counter. --}}
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
                                x-data="visualFeedbackCapture()">
                <x-wirekit::button type="button" class="visual-feedback-capture"
                    x-ref="idle"
                    x-show="status === 'idle'"
                    x-on:click="capture()">
                    {{ __('visual-feedback::messages.widget.capture_screenshot') }}
                </x-wirekit::button>

                {{-- Native capture asks the browser to share the screen — say so before the
                     dialog, and only when native can actually run (never on iOS/dom-only). --}}
                <p class="visual-feedback-native-hint" x-cloak
                    x-show="status === 'idle' && nativeAvailable()">
                    {{ __('visual-feedback::messages.widget.screenshot_native_hint') }}
                </p>

                {{-- Progress + terminal status → aria-live (the Alpine state is the only source).

                     A `div`, not the `p` this used to be, and that is a correctness fix rather
                     than taste: the attached-screenshot line below is a kit alert now, the kit
                     renders it as a `div`, and a `div` inside a `p` is invalid HTML that the
                     parser REPAIRS by closing the paragraph early. The alert would have landed
                     outside the live region that announces it, and the elements after it would
                     have moved, which is the overlap a reporter sees beside the retake button. --}}
                <div class="visual-feedback-capture-status" aria-live="polite">
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
                        <x-wirekit::alert intent="success" role="presentation" class="visual-feedback-success"
                            x-show="status === 'attached'">{{ __('visual-feedback::messages.widget.screenshot_attached') }}</x-wirekit::alert>
                    @endunless
                </div>

                {{-- Preview before submit: discard (never uploaded), retake, or attach.

                     The class marks this as the OPEN POINT while the capture is neither attached
                     nor discarded. The sentence that says so is at the end of the form, and a
                     sentence naming a state without showing which part of the screen it means
                     leaves the reader to search for it. The border is not the only carrier -- the
                     sentence stays exactly as it was, and the border is added to it. --}}
                <div class="visual-feedback-captured-pending" x-show="status === 'captured'">
                    {{-- `x-if`, NOT the `x-show` on the container, and the difference is a request per page
                         view. `x-show` sets `display:none` and leaves the element in the DOM, so before the
                         first capture every page carrying the widget held an image element whose `src` was empty.
                         An empty `src` resolves against the PAGE URL: the browser fetches the HTML document
                         as an image and throws it away, `naturalWidth` stays 0, and every browser checker
                         reads it as broken. A consumer's suite went red on 54 pages from this one element.

                         The condition is `previewUrl` rather than the status, because that is the thing
                         being asserted: the image exists exactly when it has a source.

                         AND ONLY THE IMAGE MOVES. The reported fix wrapped the whole block, which would
                         have taken `x-ref="captured"` with it -- and the capture component focuses
                         `$refs[status]` on every status change, so the successor control is reachable after
                         the reporter presses the one being swapped out. `vfFocusIfLost` returns silently on
                         a missing element, so that regression would be invisible: no error, no failing arm,
                         just focus dropping to <body> for anyone on a keyboard.

                         `loading="lazy"` is gone with it, and for two releases this sentence was
                         true while the attribute was still three lines below it. It never worked here --
                         a lazy image inside a `display:none` parent is never requested at all -- and an
                         element that only exists once it is needed has nothing left to defer.

                         The parent really does go `display:none` while this element is still rendered,
                         which is what kept it biting: `x-if` switches on `previewUrl`, the container
                         switches on `status`, and `attach()` sets `status = 'attached'` without ever
                         nulling `previewUrl` -- only `discard()` reaches `reset()`. So after attaching,
                         the image sat inside a hidden container, was never requested, and read as broken
                         to anything that checks `naturalWidth`. Same measurement as before the `x-if`,
                         one page later. --}}
                    <template x-if="previewUrl">
                        <img class="visual-feedback-preview" :src="previewUrl"
                            alt="{{ __('visual-feedback::messages.widget.screenshot_preview') }}" decoding="async">
                    </template>
                    <div class="visual-feedback-preview-actions">
                        <x-wirekit::button type="button" id="visual-feedback-screenshot" intent="primary" x-ref="captured" x-on:click="attach()">{{ __('visual-feedback::messages.widget.screenshot_attach') }}</x-wirekit::button>
                        <x-wirekit::button type="button" x-on:click="discard()">{{ __('visual-feedback::messages.widget.screenshot_discard') }}</x-wirekit::button>
                        <x-wirekit::button type="button" x-on:click="retake()">{{ __('visual-feedback::messages.widget.screenshot_retake') }}</x-wirekit::button>
                    </div>
                </div>

                <x-wirekit::button type="button" x-ref="attached" x-show="status === 'attached'"
                    x-on:click="retake()">
                    {{ __('visual-feedback::messages.widget.screenshot_retake') }}
                </x-wirekit::button>

                {{-- A capture failure is VISIBLE with retry, not a silent console.warn.

                     The region is unconditional and the ALERT inside it carries the `x-show`,
                     unlike the server-rendered region below: this one's text is always in the
                     markup and Alpine decides whether it is seen. `x-show` writes
                     `display:none` as an inline style, which outranks any stylesheet, so the
                     box cannot leak out while the capture is idle. --}}
                <div class="visual-feedback-alert" role="alert" aria-live="assertive">
                    <x-wirekit::alert intent="danger" role="presentation" class="visual-feedback-alert--shown"
                        x-show="status === 'failed'">
                        {{ __('visual-feedback::messages.widget.screenshot_failed') }}
                        <x-wirekit::button type="button" x-ref="failed" x-on:click="retake()">{{ __('visual-feedback::messages.widget.screenshot_retake') }}</x-wirekit::button>
                    </x-wirekit::alert>
                </div>

                {{-- Perimeter error surfaced from the screenshot upload validation. --}}
                <div class="visual-feedback-alert" role="alert" aria-live="assertive">
                    @error('screenshot')
                        <x-wirekit::alert intent="danger" role="presentation" class="visual-feedback-alert--shown">{{ $message }}</x-wirekit::alert>
                    @enderror
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
            id="visual-feedback-files" :error="$vfInvalidField === 'files' ? ($failedMessage ?? __('visual-feedback::messages.widget.error')) : null"
            wire:model="attachments"
            multiple
            :accept="$acceptAttribute"
            :hint="$attachmentLimit"
            {{-- WireKit's remove label is `__('Remove :name')` from ITS namespace, so without this
                 the button announced itself in WireKit's language, not the widget's — and the
                 widget ships seven locales. The prop arrived in WireKit 2.20. --}}
            :removeLabel="__('visual-feedback::messages.widget.remove_file', ['name' => ':name'])"
            :label="__('visual-feedback::messages.widget.attachments_label')" />

        {{-- Real-time perimeter errors surfaced from the upload validation.

             Both keys are tested, because both are rendered: a per-file failure lands under
             `attachments.0`, never under `attachments`, and a box keyed on the bare name alone
             would stay unpainted for exactly the rejection a reporter is most likely to hit. --}}
        <div class="visual-feedback-alert" role="alert" aria-live="assertive">
            @if ($errors->has('attachments') || $errors->has('attachments.*'))
                <x-wirekit::alert intent="danger" role="presentation" class="visual-feedback-alert--shown">
                    @error('attachments') {{ $message }} @enderror
                    @error('attachments.*') {{ $message }} @enderror
                </x-wirekit::alert>
            @endif
        </div>

        {{-- Error region. Two things are keyed on the failure COUNTER rather than on `failed`:
             that flag flips false→true inside one round-trip, so a repeat failure looks unchanged
             to Alpine and would move nothing.

             Focus goes to the control the failure belongs to, and the text is the reason the
             pipeline gave. Both used to be blunt: any failure showed one generic line and pointed
             at the message box, so a typo in the email sent the reporter to the text they had
             written correctly. --}}
        {{-- The id stays on the REGION, never on the alert inside it: it is the focus target
             `vfFocusFailedField()` falls back to, and a target that only exists while the
             failure is on screen is missing exactly when focus is moved to it. --}}
        {{-- IMPORTANT. THE BOX SUMMONS, THE FIELD EXPLAINS -- and it is one or the other, never both.
             This line used to print `$failedMessage` unconditionally, which in THIS tree put the
             identical sentence on screen twice: every control above takes the same string through
             `:error`, and the kit paints it under the field. Reported from a consumer against
             0.11.0 over both engines and both viewports, roughly 310 px apart on a desktop and 350
             on a phone -- far enough not to read as one message, near enough to be in view at
             once, so it reads as two problems and the reader goes looking for the second one.

             The plain tree never had this: it has no per-field message slot, so its controls point
             at THIS region with `aria-describedby` and the sentence exists once. The kit does have
             one, and the nearer copy is the more useful of the two -- it sits at the control the
             reporter has to change -- so that is the one that keeps the reason.

             `$vfInvalidField` is the right condition rather than `$failed`, and the difference is
             load-bearing: a listener veto, a rate limit and the master switch being off all fail
             with NO field marked, and there the box is the only place the reason can be. It keeps
             it. --}}
        <div class="visual-feedback-error visual-feedback-alert"
            id="visual-feedback-error" role="alert" aria-live="assertive"
            x-effect="$wire.failureCount && vfFocusFailedField()">
            @if ($failed)
                <x-wirekit::alert intent="danger" role="presentation" class="visual-feedback-alert--shown">{{ $vfInvalidField !== null ? __('visual-feedback::messages.widget.error_check_field') : ($failedMessage ?? __('visual-feedback::messages.widget.error')) }}</x-wirekit::alert>
            @endif
        </div>

        @if ($showGuestFields && $privacyNoticeUrl)
            {{-- `$privacyNoticeWording` is the PUBLISHED sentence when a source supplies one, else
                 null and this package's own lang line is used. Safe to hand to the component:
                 WireKit renders the label as `{{ $label }}` (verified — checkbox.blade.php:185),
                 which matters because legal-consent never runs this field through a sanitizer. --}}
            <x-wirekit::checkbox
                id="visual-feedback-privacy" :error="$vfInvalidField === 'privacy' ? ($failedMessage ?? __('visual-feedback::messages.widget.error')) : null"
                wire:model="privacyAcknowledged"
                required
                :label="$privacyNoticeWording ?? __('visual-feedback::messages.widget.privacy_acknowledge')" />
            {{-- The anchor stays a SIBLING of the checkbox here, unlike the plain tree where it
                 sits inside the `<label>`: that is what keeps the checkbox's accessible name the
                 bare acknowledgment sentence, with no link text folded into it. So the link
                 needs a text of its own, and it deliberately does NOT repeat the sentence the
                 label already carries — a second copy of it would render twice on screen and
                 read as a link that never says where it goes. It names the destination instead. --}}
            <a href="{{ $privacyNoticeUrl }}" target="_blank" rel="noopener noreferrer">
                {{ __('visual-feedback::messages.widget.privacy_notice_link') }}
            </a>
        @endif

        {{-- The star the required controls carry, spelled out. A red mark with no key is a
             convention, and a convention only works for the people who already know it.

             It is NOT aria-hidden. A screen reader announces a required control from the
             attribute itself, so this sentence is not what carries the information for that
             reader -- but it is ordinary page text, it costs nothing to hear, and hiding it
             would be the package deciding that one audience gets an explanation the other
             does not. What it must not be is a live region or an error: it says nothing has
             gone wrong.

             Rendered unconditionally, because both controls it explains are always on screen:
             `message` and `category` are required in every configuration. The guest fields and
             the privacy tick can add more, never fewer. --}}
        <p class="visual-feedback-required-legend">
            <span class="visual-feedback-required-mark" aria-hidden="true">*</span>
            {{ __('visual-feedback::messages.widget.required_legend') }}
        </p>

        <x-wirekit::button type="submit" class="visual-feedback-submit" intent="primary">
            {{ __('visual-feedback::messages.widget.submit') }}
        </x-wirekit::button>
    </form>
@endunless
