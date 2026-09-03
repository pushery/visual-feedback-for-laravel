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
<div
    {{-- Browser metadata collector. Untrusted by design — the server MetadataSanitizer
         enforces the allowlist, caps, http(s)-only URLs and never-IP. It is measured
         FRESH at open and again at submit (never frozen at page load — a viewport or
         scroll change between the two would otherwise ship stale data). darkMode reads
         the app appearance (`.dark`/`.light` on <html>) before the OS preference, so a
         light app on a dark OS is not mislabeled. `user_agent` is deliberately omitted
         here — the server overrides it with the request's own, unspoofable value. --}}
    x-data="{
        vfMeta() {
            const de = document.documentElement;
            const dark = de.classList.contains('dark')
                ? true
                : (de.classList.contains('light') ? false : window.matchMedia('(prefers-color-scheme: dark)').matches);
            return {
                url: location.href,
                title: document.title,
                referrer: document.referrer,
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight,
                screen_width: window.screen.width,
                screen_height: window.screen.height,
                device_pixel_ratio: window.devicePixelRatio,
                scroll_y: window.scrollY,
                language: navigator.language,
                languages: (navigator.languages || []).join(','),
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                platform: navigator.platform,
                touch: ('ontouchstart' in window) || navigator.maxTouchPoints > 0,
                online: navigator.onLine,
                cookies_enabled: navigator.cookieEnabled,
                dark_mode: dark,
            };
        },

        {{-- Focus rescue. Whenever a control disappears because the state moved on — the
             capture button while capturing, the discard button after discarding, the last
             remove button after removing it — the browser drops focus onto <body> (or, in a
             modal, onto the <dialog> itself) and the keyboard user is back at the top. This
             puts focus on the control that took its place, but ONLY when it was actually
             lost: if the reporter has meanwhile tabbed somewhere themselves, moving their
             focus would be the worse bug. --}}
        vfFocusIfLost(el, outgoing = document.activeElement, attempts = 12) {
            if (! el) {
                return;
            }

            const active = document.activeElement;

            {{-- Focus is LOST when it sits on <body>, on the dialog itself, or on an element
                 that no longer has a box — hiding the focused control does not blur it
                 synchronously, so it can still be document.activeElement while rendering
                 nothing. --}}
            const lost = ! active || active === document.body || active.tagName === 'DIALOG'
                || active.getClientRects().length === 0;

            if (lost) {
                {{-- focus() on a display:none element does not throw, it silently does
                     nothing — so wait until the successor is actually rendered. --}}
                if (el.getClientRects().length > 0) {
                    el.focus();
                } else {
                    attempts > 0 && requestAnimationFrame(() => this.vfFocusIfLost(el, outgoing, attempts - 1));
                }

                return;
            }

            {{-- Not lost YET, and that is the trap: for a frame or two after the state
                 changed, the control being replaced still holds focus AND still has a box,
                 so a single verdict taken here always reads "fine" and the drop to <body>
                 happens with nobody watching. Keep looking while focus still sits on that
                 outgoing control — and the moment it rests anywhere the reporter put it,
                 stop and leave it alone. --}}
            if (active === outgoing && attempts > 0) {
                requestAnimationFrame(() => this.vfFocusIfLost(el, outgoing, attempts - 1));
            }
        },
    }"
    {{-- Single open handler for ALL three trigger paths (built-in FAB, standalone
         <x-visual-feedback::fab> / <x-visual-feedback::trigger>, and a host's own
         $dispatch('visual-feedback:open')). Each dispatches this window event; here we
         re-measure metadata and open the native modal. --}}
    x-on:visual-feedback:open.window="$refs.dialog && !$refs.dialog.open && ($wire.metadata = vfMeta(), $wire.markOpened(), $refs.dialog.showModal())"
>
    @if ($showFab)
        {{-- Built-in FAB (modal mode) — the same component a host can also place itself. --}}
        <x-visual-feedback::fab :position="$fabPosition" />
    @endif

    {{-- The panel. In modal mode it is a real modal <dialog> (top layer, focus-trapped by the
         browser); in inline mode it is that same element with `open` on it.

         That is NOT the same as in-flow, which this comment claimed until the layout was
         measured: the UA stylesheet gives every <dialog> `position: absolute` and only
         `dialog:modal` `position: fixed`. An open inline dialog therefore contributes nothing
         to its container's height and centers itself in the viewport rather than in its own
         column, so the stylesheet puts it back into the flow explicitly — see
         `.visual-feedback-dialog[open]:not(:modal)`. Both modes carry the same semantic class
         so a host styles one surface. --}}
    <dialog
        x-ref="dialog"
        @if ($mode === 'inline')
            open
        @else
            {{-- Preserve the native modal (top-layer) state across Livewire morphs: the
                 form inside re-renders on submit, but the dialog element's own open state
                 is NOT morphed away, so the success stays visible. Without this the server
                 (which renders the dialog closed) would drop the JS-opened state. --}}
            wire:ignore.self
        @endif
        class="visual-feedback-dialog"
        aria-label="{{ __('visual-feedback::messages.widget.heading') }}"
    >
        @if ($mode === 'modal')
            <button
                type="button"
                class="visual-feedback-close"
                aria-label="{{ __('visual-feedback::messages.widget.close') }}"
                x-on:click="$refs.dialog.close()"
            >&times;</button>
        @endif

        {{-- Status region: always present, initially empty, so a screen reader announces
             the success without a focus jump. --}}
        <div role="status" aria-live="polite"
            {{-- Focus management: after a successful submit the form is gone, so move focus
                 to the "report another" button instead of letting it fall to <body>. --}}
            x-effect="$wire.submitted && $nextTick(() => $refs.reportAnother && $refs.reportAnother.focus())">
            @if ($submitted)
                <p>{{ __('visual-feedback::messages.widget.success') }}</p>
                {{-- Resetting removes this very button, so the round-trip hands focus on to
                     the message field of the fresh form instead of leaving it on <body>. --}}
                <button type="button" x-ref="reportAnother"
                    x-on:click="$wire.resetWidget().then(() => $nextTick(() => vfFocusIfLost(document.getElementById('visual-feedback-message'))))">
                    {{ __('visual-feedback::messages.widget.report_another') }}
                </button>
            @endif
        </div>

        @unless ($submitted)
            {{-- Re-measure metadata in the capture phase, before Livewire's own submit
                 handler fires, so the report carries the state AT submit — not at open. --}}
            <form wire:submit="submit" novalidate x-on:submit.capture="$wire.metadata = vfMeta()">
                {{-- The modal's opening focus is ANCHORED here, not left to the UA. Without an
                     autofocus target the browser picks the first focusable descendant — today
                     the close button, which tells a screen-reader user nothing about what just
                     opened. Focusing the heading announces the dialog by name first. Modal only:
                     in inline mode the widget is part of the page and must never steal focus on
                     load. tabindex="-1" makes the heading focusable without adding a tab stop. --}}
                <h2 id="visual-feedback-heading" tabindex="-1" @if ($mode === 'modal') autofocus @endif>
                    {{ __('visual-feedback::messages.widget.heading') }}
                </h2>

                {{-- Honeypot: off-screen, hidden from assistive tech, never tab-reachable.
                     Bots fill it; a non-empty value is silently rejected by the pipeline. --}}
                <div class="visual-feedback-honeypot" aria-hidden="true"
                    {{-- ⚠️ BOTH mechanisms, deliberately, and neither is redundant.
                         The inline style is what hides this on an installation that never
                         included the stylesheet. The CLASS is what hides it when a content
                         security policy allows the stylesheet (nonce or hash) but forbids
                         style ATTRIBUTES -- `style-src-attr 'none'` is ordinary hardening,
                         and under it an attribute-only concealment is simply dropped.
                         What is at stake is not cosmetic: an exposed honeypot is filled in
                         by real reporters, and a honeypot hit shows the success screen ON
                         PURPOSE. Both sides then believe a report was delivered that
                         nobody will ever see. --}}
                    style="position:absolute;width:1px;height:1px;overflow:hidden;left:-9999px;">
                    <label>
                        {{ __('visual-feedback::messages.widget.honeypot_label') }}
                        <input type="text" wire:model="feedbackReference" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                {{-- Challenge region: whatever `abuse.challenge_view` names, or nothing at all.
                     wire:ignore is load-bearing, not defensive — a challenge widget is third-party
                     DOM with its own JavaScript, and Livewire's morphing would tear it out from
                     under itself on the next update. That is the failure everyone wiring one of
                     these by hand hits first. The region is OURS (spacing, position in the form);
                     the markup inside is the host's.

                     `overflow-x:auto` is not cosmetic. Measured in a real browser at a 320px
                     viewport: the form gives this region 238px, and a Turnstile widget is 300px
                     wide by specification. Without it the widget overflows by 64px, the PAGE does
                     not scroll, and the right-hand part of a challenge a reporter has to solve is
                     simply unreachable. The style is inline rather than in the package stylesheet
                     because the WireKit tree does not load that sheet. --}}
                @if ($challengeView)
                    <div class="visual-feedback-challenge" style="overflow-x:auto" wire:ignore>
                        @includeIf($challengeView)
                    </div>
                @endif

                {{-- Guest identity fields — only for unauthenticated reporters; an authed
                     user's reporter is resolved from the guard, so these are ignored. --}}
                @if ($showGuestFields)
                    <label for="visual-feedback-name">
                        {{ __('visual-feedback::messages.widget.name_label') }}
                    </label>
                    <input id="visual-feedback-name" @if ($vfInvalidField === 'name') aria-invalid="true" aria-describedby="visual-feedback-error" @endif type="text" wire:model="guestName" autocomplete="name">

                    <label for="visual-feedback-email">
                        {{ __('visual-feedback::messages.widget.email_label') }}
                    </label>
                    <input id="visual-feedback-email" @if ($vfInvalidField === 'email') aria-invalid="true" aria-describedby="visual-feedback-error" @endif type="email" wire:model="guestEmail" autocomplete="email">

                    @if ($showPhone)
                        <label for="visual-feedback-phone">
                            {{ __('visual-feedback::messages.widget.phone_label') }}
                        </label>
                        <input id="visual-feedback-phone" @if ($vfInvalidField === 'phone') aria-invalid="true" aria-describedby="visual-feedback-error" @endif type="tel" wire:model="guestPhone" autocomplete="tel">
                    @endif
                @endif

                <label for="visual-feedback-category">
                    {{ __('visual-feedback::messages.widget.category_label') }}
                </label>
                <select id="visual-feedback-category" @if ($vfInvalidField === 'category') aria-invalid="true" aria-describedby="visual-feedback-error" @endif wire:model="category">
                    @foreach ($categoryOptions as $key => $label)
                        <option value="{{ $key }}" wire:key="vf-cat-{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if ($showSubject)
                    <label for="visual-feedback-subject">
                        {{ __('visual-feedback::messages.widget.subject_label') }}
                    </label>
                    <input id="visual-feedback-subject" @if ($vfInvalidField === 'subject') aria-invalid="true" aria-describedby="visual-feedback-error" @endif type="text" wire:model="subject">
                @endif

                {{-- The counter's locale comes from the app (never a hardcoded literal
                     such as 'de-DE'), so the thousands separator matches the
                     reporter's language. --}}
                <div x-data="{
                    count: 0,
                    {{-- The value the live region carries, kept apart from `count` on purpose:
                         the visible number has to move with the keystroke and the announced one
                         must not. --}}
                    announced: '',
                    settle: null,
                    max: {{ $messageMax }},
                    locale: @js($appLocale),
                    init() {
                        {{-- Populate the region once at boot, so its tally is readable before the
                             first keystroke rather than only after one. --}}
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
                    <label for="visual-feedback-message">
                        {{ __('visual-feedback::messages.widget.message_label') }}
                    </label>
                    {{-- `required` states what the server already enforces (SubmitReport's rules),
                         which until now the control never said: the reporter learned it from the
                         rejection. Safe here and nowhere near a native validation bubble in the
                         browser's own language — the form carries `novalidate`, so every rejection
                         still comes back through this package's own seven locales. --}}
                    <textarea id="visual-feedback-message" @if ($vfInvalidField === 'message') aria-invalid="true" aria-describedby="visual-feedback-error" @endif wire:model="message" required
                        x-on:input="measure($event.target.value)"
                        placeholder="{{ __('visual-feedback::messages.widget.message_placeholder') }}"></textarea>
                    {{-- Live character counter: code points (spread, not UTF-16 units) to
                         match the server's mb_strlen validation, so client and server judge
                         the exact same length; Intl.NumberFormat(appLocale) for the display.

                         It used to be ONE element that was both the visible counter and an
                         `aria-live` region rewritten on every keystroke — the established
                         anti-pattern for a character counter, and the reason GOV.UK's own
                         component debounces its status region instead. Two elements now: the
                         visible tally updates live for the eye and is out of the accessibility
                         tree, and a separate off-screen region takes the value only once typing
                         has paused. The region is never toggled on and off — an `aria-live`
                         element that appears in the same tick as its text can have that first
                         change swallowed, which is why the throttle is on the CONTENT. --}}
                    <span class="visual-feedback-counter" aria-hidden="true" x-text="tally()">0</span>
                    <span class="visual-feedback-sr-only" aria-live="polite" x-text="announced"></span>
                </div>

                @if ($screenshotEnabled)
                    {{-- Screenshot capture. The tree-agnostic JS module (capture.js) drives
                         the state machine; this tree renders it. $wire.upload is the swappable
                         uploader seam. The reporter SEES the PNG (preview) and can discard or
                         retake BEFORE submit — a GDPR-transparency requirement. All status text
                         comes from lang, never the JS bundle. --}}
                    {{-- Every capture step swaps the visible control, and the one being swapped
                         out is the one the keyboard user just pressed. Each terminal state names
                         its successor via x-ref, so focus lands there instead of on <body>; the
                         transient states (capturing, uploading) name nothing and are simply
                         skipped, and the next terminal state picks focus back up. --}}
                    <div class="visual-feedback-screenshot"
                        x-init="$watch('status', (value) => vfFocusIfLost($refs[value]))"
                        x-data="visualFeedbackCapture({ ...@js($screenshotCaptureConfig), upload: (file) => new Promise((resolve, reject) => $wire.upload('screenshot', file, resolve, reject)), onStage: (stage) => $wire.set('screenshotStage', stage) })">
                        <button type="button" class="visual-feedback-capture"
                            x-ref="idle"
                            x-show="status === 'idle'"
                            x-on:click="capture(document.documentElement)">
                            {{ __('visual-feedback::messages.widget.capture_screenshot') }}
                        </button>

                        {{-- Native capture asks the browser to share the screen — say so before the
                             dialog, and only when native can actually run (never on iOS/dom-only). --}}
                        <p class="visual-feedback-native-hint" x-cloak
                            x-show="status === 'idle' && nativeAvailable()">
                            {{ __('visual-feedback::messages.widget.screenshot_native_hint') }}
                        </p>

                        {{-- Progress + terminal status → aria-live ($wire.upload fires no
                             livewire-upload-* events, so this Alpine state is the only source). --}}
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
                                <button type="button" x-ref="captured" x-on:click="attach()">{{ __('visual-feedback::messages.widget.screenshot_attach') }}</button>
                                <button type="button" x-on:click="discard()">{{ __('visual-feedback::messages.widget.screenshot_discard') }}</button>
                                <button type="button" x-on:click="retake(document.documentElement)">{{ __('visual-feedback::messages.widget.screenshot_retake') }}</button>
                            </div>
                        </div>

                        <button type="button" x-ref="attached" x-show="status === 'attached'"
                            x-on:click="retake(document.documentElement)">
                            {{ __('visual-feedback::messages.widget.screenshot_retake') }}
                        </button>

                        {{-- B7 fix: a capture failure is VISIBLE with retry, not a silent console.warn. --}}
                        <div x-show="status === 'failed'" role="alert" aria-live="assertive">
                            {{ __('visual-feedback::messages.widget.screenshot_failed') }}
                            <button type="button" x-ref="failed" x-on:click="retake(document.documentElement)">{{ __('visual-feedback::messages.widget.screenshot_retake') }}</button>
                        </div>

                        {{-- Perimeter error surfaced from the screenshot upload validation. --}}
                        <div role="alert" aria-live="assertive">
                            @error('screenshot') {{ $message }} @enderror
                        </div>
                    </div>
                @endif

                {{-- Attachments. The file input is VISIBLE and focusable — the native
                     keyboard path (Enter/Space opens the picker) — not a hidden-input-over-
                     zone that only works for touch. `accept` is derived from the
                     server allowlist, so it can never drift from what validation permits. --}}
                <div class="visual-feedback-attachments">
                    <label for="visual-feedback-files">
                        {{ __('visual-feedback::messages.widget.attachments_label') }}
                    </label>
                    <input
                        id="visual-feedback-files" @if ($vfInvalidField === 'files') aria-invalid="true" aria-describedby="visual-feedback-error" @endif
                        class="visual-feedback-file-input"
                        type="file"
                        multiple
                        accept="{{ $acceptAttribute }}"
                        aria-describedby="visual-feedback-files-limit"
                        wire:model="attachments"
                    >

                    {{-- The caps in words, BEFORE the reporter picks. They were server-side only:
                         pick five files where four are allowed and the rejection was the first you
                         heard of it. `aria-describedby` rather than loose text, so a
                         screen reader reads the limit as part of the field. --}}
                    <p id="visual-feedback-files-limit" class="visual-feedback-hint">{{ $attachmentLimit }}</p>

                    {{-- Real-time perimeter errors (invalid type / too large / too many). --}}
                    <div role="alert" aria-live="assertive">
                        @error('attachments') {{ $message }} @enderror
                        @error('attachments.*') {{ $message }} @enderror
                    </div>

                    @if (count($attachments) > 0)
                        <ul class="visual-feedback-file-list" aria-live="polite">
                            @foreach ($attachments as $index => $file)
                                {{-- The store loop skips an entry that is not an upload; so does
                                     this one. Without the skip a single malformed entry takes the
                                     whole widget render down with a call on null — the reporter
                                     sees an error page instead of the form, for a file they could
                                     simply have removed. --}}
                                @continue (! $file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                                {{-- The key is the file's own temporary name, never the loop index.
                                     Removing an entry re-indexes the rest, so an index key made the
                                     morph keep the focused button alive while its meaning silently
                                     moved to the NEXT file — press Enter twice and two different
                                     files are gone, with nothing announced in between. --}}
                                <li class="visual-feedback-file" wire:key="vf-file-{{ $file->getFilename() }}">
                                    <span class="visual-feedback-file-name">{{ $file->getClientOriginalName() }}</span>
                                    <button
                                        type="button"
                                        class="visual-feedback-remove"
                                        {{-- Removing the row removes this button; hand focus to the
                                             one that slid into its place, or to the file input when
                                             the list is now empty.

                                             The container is captured BEFORE the round trip, and
                                             that is the whole point: removing this row detaches
                                             this very button, so `$el.closest(…)` afterwards is
                                             null and the handler dies on a TypeError before it ever
                                             reaches vfFocusIfLost — focus then drops to <body> with
                                             nothing said. Held in a local, the container survives
                                             the morph because the list element itself is keyed and
                                             kept. --}}
                                        x-on:click="(() => {
                                            const box = $el.closest('.visual-feedback-attachments');
                                            $wire.removeAttachment({{ $index }}).then(() => $nextTick(() => {
                                                const remaining = box.querySelectorAll('.visual-feedback-remove');
                                                vfFocusIfLost(remaining[Math.min({{ $index }}, remaining.length - 1)] ?? document.getElementById('visual-feedback-files'));
                                            }));
                                        })()"
                                        aria-label="{{ __('visual-feedback::messages.widget.remove_file', ['name' => $file->getClientOriginalName()]) }}"
                                    >&times;</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Error region: always present, initially empty (assertive so failures
                     interrupt). On failure focus goes to the field the failure BELONGS to, and
                     the effect keys on the failure COUNTER rather than on the boolean: `failed`
                     goes false→true within one round-trip, so a second failure in a row would
                     look unchanged to Alpine and move nothing. --}}
                {{-- The failed field names its own control, so focus lands where the problem is.
                     This used to be a two-way choice — privacy, or the message box for everything
                     else — so a reporter with a typo in their email was pointed at the message
                     they had written correctly. An unknown field still falls back
                     to the message box rather than dropping focus on <body>. --}}
                <div class="visual-feedback-error" id="visual-feedback-error" role="alert" aria-live="assertive"
                    x-effect="$wire.failureCount && $nextTick(() => document.getElementById('visual-feedback-' + (['category','subject','message','name','email','phone','privacy','files'].includes($wire.failedField) ? $wire.failedField : 'message'))?.focus())">
                    @if ($failed){{ $failedMessage ?? __('visual-feedback::messages.widget.error') }}@endif
                </div>

                {{-- Guest privacy acknowledgement — required only when a notice URL is configured.
                     `required` can be stated unconditionally INSIDE this branch because the branch
                     is the same condition the server checks: PrivacyNotice::required() is literally
                     `url() !== null`, and submit() rejects on exactly that. The two cannot drift.
                     Like the message field it is safe next to `novalidate` — no native bubble, no
                     browser-language text. --}}
                @if ($showGuestFields && $privacyNoticeUrl)
                    <label class="visual-feedback-privacy" for="visual-feedback-privacy">
                        <input id="visual-feedback-privacy" @if ($vfInvalidField === 'privacy') aria-invalid="true" aria-describedby="visual-feedback-error" @endif type="checkbox" wire:model="privacyAcknowledged" required>
                        {{-- The link is what makes the acknowledgement informed, so it stays the
                             anchor whatever the wording says. `$privacyNoticeWording` is the
                             PUBLISHED sentence when a source supplies one; it is plain text that
                             legal-consent never sanitizes, so it is escaped here like any other
                             untrusted string.

                             Because the anchor is interactive content, a pointer landing on it
                             navigates and runs no label activation behavior at all — so the
                             tickable target is the column the stylesheet keeps to its left, not
                             the sentence. That is what `.visual-feedback-privacy` sizes. --}}
                        <a href="{{ $privacyNoticeUrl }}" target="_blank" rel="noopener noreferrer">
                            {{ $privacyNoticeWording ?? __('visual-feedback::messages.widget.privacy_acknowledge') }}
                        </a>
                    </label>
                @endif

                <button type="submit">{{ __('visual-feedback::messages.widget.submit') }}</button>
            </form>
        @endunless
    </dialog>
</div>
