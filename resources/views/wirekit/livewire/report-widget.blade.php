{{--
    WireKit report-widget view — the token-based pendant of the plain
    livewire/report-widget.blade.php. Published over the same vendor paths via the
    `visual-feedback-wirekit` tag, so a WireKit app renders this instead of the plain tree.
    Modal mode uses <x-wirekit::modal> (opened by the `wirekit-modal-show` event); inline
    mode renders the form in a <x-wirekit::card>. The metadata collector and the open
    handler are framework-agnostic and mirror the plain tree.
--}}
<div
    {{-- The same registered component the plain tree uses. It moved into the bundle because an
         object literal with method shorthand, statements, default parameters, arrow functions
         and bare `document`/`window` is outside Alpine's CSP grammar on six counts — and a
         rejected `x-data` leaves the element with an EMPTY scope, so every directive beneath it
         silently stops working. --}}
    x-data="visualFeedbackWidget()"
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

         Nothing is lost by dropping the listener inline: `mount()` stamps `openedAt` for an
         INLINE widget — it is open from the moment it renders, and nothing else would ever
         stamp it, because this listener is the modal path — and the metadata is re-measured on
         the form's own `submit.capture`. (A modal is deliberately NOT stamped at mount; that is
         what keeps an unopened widget's markup identical from one second to the next.) --}}
    @if ($mode === 'modal')
        x-on:visual-feedback:open.window="vfOpenWireKit()"
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
