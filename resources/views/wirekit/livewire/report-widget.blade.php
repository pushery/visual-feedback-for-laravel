{{--
    WireKit report-widget view — the token-based pendant of the plain
    livewire/report-widget.blade.php. Published over the same vendor paths via the
    `visual-feedback-wirekit` tag, so a WireKit app renders this instead of the plain tree.
    Modal mode uses <x-wirekit::modal> (opened by the `wirekit-modal-show` event); inline
    mode renders the form in a <x-wirekit::card>. The metadata collector and the open
    handler are framework-agnostic and mirror the plain tree.
--}}
<div
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

        {{-- Focus rescue — the plain tree's twin (see livewire/report-widget.blade.php).
             Restores focus to the control that replaced a disappearing one, and only when
             focus was genuinely dropped onto <body>, never when the reporter moved it. --}}
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
    {{-- Every trigger path (built-in FAB, standalone components, a host's own $dispatch)
         dispatches `visual-feedback:open`; here we re-measure metadata fresh and open the
         WireKit modal by name via its own `wirekit-modal-show` event.

         MODAL ONLY, and the listener is not merely inert inline — it is harmful there. An
         inline widget renders a card and no modal at all, so there is nothing to open; but
         `markOpened()` would still re-anchor the server-side `openedAt`, and the abuse gate
         rejects anything submitted within `abuse.min_fill_seconds` of that stamp as a
         honeypot hit — SILENTLY, with the decoy success shown. The listener carries
         `.window`, and a mixed page (one inline widget plus one modal, which the docs
         support) is exactly where a FAB press meant for the modal would reach the inline
         widget and reset the timer under someone mid-sentence.

         The plain tree is guarded by accident rather than by intent: its inline `<dialog>`
         renders with a literal `open`, so `!$refs.dialog.open` is permanently false there.
         Same outcome, so this is parity — not a new rule.

         Nothing is lost by dropping the listener inline: `mount()` stamps `openedAt`
         unconditionally ("the inline widget is open from the moment it renders"), and the
         metadata is re-measured on the form's own `submit.capture`. --}}
    @if ($mode === 'modal')
        x-on:visual-feedback:open.window="$wire.metadata = vfMeta(); $wire.markOpened(); $dispatch('wirekit-modal-show', { name: 'visual-feedback' })"
    @endif
>
    @if ($mode === 'modal')
        @if ($showFab)
            <x-visual-feedback::fab :position="$fabPosition" />
        @endif

        {{-- The dialog is named through WireKit's own header, never with aria-label on the
             component tag. <x-wirekit::modal> forwards stray attributes to its outer wrapper —
             a <div> with no role — where aria-label is a prohibited attribute that assistive
             tech ignores outright, while the panel's aria-labelledby kept pointing at the
             header id that then never existed. The result was a dialog with NO accessible
             name at all. modal.header supplies that id, and with it the keyboard-reachable
             close button the plain tree already had. --}}
        <x-wirekit::modal name="visual-feedback">
            <x-wirekit::modal.header>
                <h2 id="visual-feedback-heading" tabindex="-1">
                    {{ __('visual-feedback::messages.widget.heading') }}
                </h2>
            </x-wirekit::modal.header>

            <x-wirekit::modal.body class="visual-feedback-panel">
                @include('visual-feedback::wirekit.livewire._report-form', ['headingInHeader' => true])
            </x-wirekit::modal.body>
        </x-wirekit::modal>
    @else
        {{-- `visual-feedback-panel` is the marker the capture module hides by. The plain tree's
             panel is a <dialog> and was found by element type; this one is a WireKit component,
             so without a marker of our own every DOM-stage screenshot from a WireKit app
             carried a picture of the feedback form instead of the page being reported. It
             cannot go on <x-wirekit::modal> itself: stray attributes land on a wrapper OUTSIDE
             the x-teleport, not on the overlay that paints over the page. --}}
        <x-wirekit::card class="visual-feedback-panel">
            @include('visual-feedback::wirekit.livewire._report-form')
        </x-wirekit::card>
    @endif
</div>
