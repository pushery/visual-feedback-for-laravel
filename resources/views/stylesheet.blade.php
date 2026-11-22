{{--
    The plain tree's stylesheet as text: its palette, with the dark colors under the operating
    system's preference or under `ui.dark_selector`, and every rule of its panel. The WireKit tree
    gets `stylesheet-wirekit.blade.php` instead, and Pushery\VisualFeedback\Support\Stylesheet
    chooses between the two by the template the widget resolves to.

    `@include('visual-feedback::style')` links this text as a file the package serves, or writes it
    into the page where the host asks for that. The digest in the address is taken over what this
    view renders, so an edit to a published copy of it, or a change of the dark-mode keys, moves the
    address with it.

    The view holds no Blade condition, and that is deliberate. While a Livewire component renders,
    Livewire writes a morph marker around every condition of every view rendered with it, except
    inside a script or a style element, and a component's view may carry the include. This view
    renders on its own, so a marker would land in the text: it would start a selector and take the
    rule after it down, and the digest of a page's link would no longer match the sheet the route
    serves.
--}}
@php
    // The dark colors stand under the operating system's preference, or under the application's
    // own selector where `ui.dark_mode` is `class`. Resolved here, so the one copy of the dark
    // values below is wrapped by whichever rule applies; DarkScheme holds the selector to what
    // cannot leave that rule, because it is written into the sheet as it stands.
    $vfDarkSelector = \Pushery\VisualFeedback\Support\DarkScheme::selector(
        config('visual-feedback.ui.dark_mode'),
        config('visual-feedback.ui.dark_selector'),
    );
    $vfDarkOpen = $vfDarkSelector === null ? '@media (prefers-color-scheme: dark) { :root {' : $vfDarkSelector.' {';
    $vfDarkClose = $vfDarkSelector === null ? '} }' : '}';
@endphp
    :root {
        --vf-bg: #ffffff;
        --vf-fg: #111827;
        --vf-muted: #6b7280;
        {{-- One border value for both schemes: 3.82:1 on the light surface and 3.84:1 on the
           dark one, so the 1px boundary of an input, the file dropzone and a remove button
           clears the 3:1 WCAG 1.4.11 floor either way. The conventional light gray (#d1d5db)
           measured 1.47:1 — a boundary nobody with low vision can find. Because one value
           carries both schemes, the dark block below does not override it. --}}
        --vf-border: #7b8390;
        --vf-accent: #2563eb;
        --vf-accent-fg: #ffffff;
        --vf-error: #b91c1c;
        {{-- The one tone that says a thing worked. Measured on the surface it sits on rather than
           picked: 5.02:1 on #ffffff and 8.42:1 on the dark #1f2937, so both clear the 4.5:1 AA
           floor for body text. Per-scheme like --vf-error and unlike --vf-border, because a
           single green cannot carry both. --}}
        --vf-success: #15803d;
        {{-- The surface of a secondary button, so every button has a fill: #e5e7eb under --vf-fg
           measures 14.33:1, and the 1px --vf-border keeps the boundary it gives against the
           panel, 3.82:1 on #ffffff. --}}
        --vf-surface: #e5e7eb;
        {{-- The tone of a choice still open, not of a failure: the border of a capture that is
           neither attached nor discarded. A 1px boundary, so the floor is the 3:1 of WCAG 1.4.11:
           5.02:1 on #ffffff here and 6.83:1 on the dark #1f2937 below. --}}
        --vf-warning: #b45309;
        --vf-backdrop: rgba(17, 24, 39, .5);
        {{-- Two radii, one per scale: the panels round with --vf-radius, and everything inside them
           with --vf-radius-control, fields, buttons, status boxes and previews, in the widget and
           the report browser alike. Overriding one changes that scale everywhere it appears. --}}
        --vf-radius: 10px;
        --vf-radius-control: 6px;
        --vf-fab-size: 2.75rem;
        --vf-gap: 1rem;
        {{-- The scheme the browser paints its own controls in on the package's surfaces: the
           checkbox, the native file button, the select popup and the privacy link. Beside the
           colors, so the two switch together and cannot disagree: light here, dark wherever the
           dark colors below apply. --}}
        --vf-color-scheme: light;
    }

    {{-- Stands after the light colors, so a selector of class weight wins on <html> too. --}}
    {!! $vfDarkOpen !!}
            --vf-bg: #1f2937;
            --vf-fg: #f9fafb;
            --vf-muted: #9ca3af;
            {{-- The accent carries two jobs that pull against each other on the dark surface:
               it is the fill under a white label (needs 4.5:1 against #ffffff) and it is the
               focus ring and the button boundary (needs 3:1 against --vf-bg). The window
               between those is narrow — #2f6fe4 sits in it at 4.65:1 and 3.16:1, where the
               lighter #3b82f6 renders the white label at 3.68:1. --}}
            --vf-accent: #2f6fe4;
            --vf-accent-fg: #ffffff;
            --vf-error: #f87171;
            --vf-success: #4ade80;
            {{-- 9.86:1 under the dark --vf-fg, and the border keeps 3.84:1 against #1f2937. --}}
            --vf-surface: #374151;
            --vf-warning: #f59e0b;
            --vf-backdrop: rgba(0, 0, 0, .6);
            --vf-color-scheme: dark;
    {!! $vfDarkClose !!}

    {{-- x-cloak gotcha: without this rule an [x-cloak] element flashes before
       Alpine boots. Shipping the rule with the stylesheet keeps it never-undefined. --}}
    [x-cloak] { display: none !important; }

    {{-- Single-action FAB: fixed, ≥ 44px AAA target, composes the iOS safe-area insets. --}}
    .visual-feedback-fab {
        {{-- Tell the UA which scheme this surface is painted in. The tokens above flip
           themselves dark, but anything the browser draws — link color, the native "Choose
           file" button, the checkbox, the select popup, the default focus ring — stays in
           light-scheme colors unless it is told. Untold, the privacy link rendered at
           1.56:1 on the dark dialog. It is set on the package's own surfaces, never on
           :root: a library must not repaint the host application's form controls. The value
           is the one --vf-color-scheme names beside the colors, so a widget that follows the
           application's dark class is told the application's scheme, not the system's. --}}
        color-scheme: var(--vf-color-scheme, light dark);
        position: fixed;
        z-index: 2147483000;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
        min-height: 44px;
        padding: 0 1.25rem;
        height: var(--vf-fab-size);
        border: 0;
        border-radius: 999px;
        background: var(--vf-accent);
        color: var(--vf-accent-fg);
        font: inherit;
        font-weight: 600;
        {{-- A pill button is one line. It inherits the host's font, so a wider face or a
           longer translation ("Feedback senden") wraps it into a two-line lump that no
           longer reads as a button — seen for real in a consumer app, where the capture's
           font metrics differed just enough to break it. --}}
        white-space: nowrap;
        cursor: pointer;
        box-shadow: 0 6px 20px rgba(0, 0, 0, .18);
    }
    .visual-feedback-fab:focus-visible { outline: 3px solid var(--vf-accent); outline-offset: 2px; }

    {{-- The corner is logical, in this tree as in the WireKit one: `end` is the side a line of
       text ends on, so a right-to-left page mirrors the trigger the way it mirrors the rest of
       its layout. The safe-area insets are physical, so each direction names the one on its own
       side; a browser without :dir() drops that rule and keeps the left-to-right pairing, which
       only a landscape notch would show. The physical class names are selectors too, for a fab
       component published before the corners were logical. --}}
    .visual-feedback-fab { --vf-safe-end: env(safe-area-inset-right, 0px); --vf-safe-start: env(safe-area-inset-left, 0px); }
    .visual-feedback-fab:dir(rtl) { --vf-safe-end: env(safe-area-inset-left, 0px); --vf-safe-start: env(safe-area-inset-right, 0px); }
    .visual-feedback-fab--bottom-end,   .visual-feedback-fab--bottom-right { bottom: calc(var(--vf-gap) + env(safe-area-inset-bottom, 0px)); inset-inline-end: calc(var(--vf-gap) + var(--vf-safe-end)); }
    .visual-feedback-fab--bottom-start, .visual-feedback-fab--bottom-left  { bottom: calc(var(--vf-gap) + env(safe-area-inset-bottom, 0px)); inset-inline-start: calc(var(--vf-gap) + var(--vf-safe-start)); }
    .visual-feedback-fab--top-end,      .visual-feedback-fab--top-right    { top: calc(var(--vf-gap) + env(safe-area-inset-top, 0px)); inset-inline-end: calc(var(--vf-gap) + var(--vf-safe-end)); }
    .visual-feedback-fab--top-start,    .visual-feedback-fab--top-left     { top: calc(var(--vf-gap) + env(safe-area-inset-top, 0px)); inset-inline-start: calc(var(--vf-gap) + var(--vf-safe-start)); }

    {{-- Native <dialog>: the browser provides the top layer, focus trap, Esc-to-close and
       focus return to the trigger, so this sheet only styles the surface. --}}
    .visual-feedback-dialog {
        color-scheme: var(--vf-color-scheme, light dark);   {{-- see the FAB rule above — it inherits to every control inside --}}
        {{-- border-box, or the width below is only the content: the 1.5rem padding and the 1px
           border are then added on top and the panel is 50px wider than it says. On a 320px
           phone that is the difference between fitting and the page scrolling sideways. --}}
        box-sizing: border-box;
        {{-- The UA centers a modal <dialog> with `margin: auto`, and Tailwind's preflight
           (`*, ::after, ::before, ::backdrop { margin: 0 }`) takes it away — so in a Tailwind
           host, which is most Laravel apps, the panel lands in the top-left corner. Measured
           in a real WireKit app: top/left 0 instead of 130/384 at 1280x900. Restated here for
           the same reason box-sizing, padding and border above are restated: this stylesheet
           may not assume it is the last word on the element. --}}
        margin: auto;
        width: min(32rem, calc(100vw - 2rem));
        {{-- dvh, not vh. On a phone `vh` is the viewport with the browser UI retracted, so a
           dialog sized in vh is taller than the space actually on screen while the URL bar is
           showing — its submit button sits under the chrome and the reporter cannot reach it.
           The vh line stays as the fallback for engines without dvh.

           The same 1rem margin as the width, not a share of the height. At 80% of a 568px
           phone the panel left 114px of the screen unused while the form scrolled inside it,
           and after an empty submit Send sat partly under the panel's edge with any common
           host font. With the margin the panel gets 536px there, and the failed field, the
           failure and Send fit together. From 928px of height up, 56rem decides either way, and the
           whole form fits: at 40rem the panel scrolled on every desktop, and a hint stood cut in the
           middle of a sentence at its edge. --}}
        max-height: min(calc(100vh - 2rem), 56rem);
        max-height: min(calc(100dvh - 2rem), 56rem);
        padding: 1.5rem;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius);
        background: var(--vf-bg);
        color: var(--vf-fg);
        overflow: auto;
    }
    .visual-feedback-dialog::backdrop { background: var(--vf-backdrop); }

    {{-- Inline mode shares this element — the template only adds `open` — and that is the whole
       problem: the UA stylesheet gives `dialog` `position: absolute` and only `dialog:modal`
       `position: fixed`. An absolutely positioned panel does three things at once, and the width
       is the least of them:

         - it contributes nothing to its container's height, so whatever the host puts after the
           form is overlaid by it;
         - with no positioned ancestor it resolves against the initial containing block, so the
           UA's `inset-inline: 0` plus the `margin: auto` restated above center it in the
           viewport — measured 632px to the left of its own 264px sidebar at a 1280px viewport,
           i.e. across the main column, not merely overhanging its own;
         - only then does `100vw` in the width above track the viewport instead of the column.

       `position: static` is the one declaration that answers all three, and `min(32rem, 100%)`
       only means "the column" once it is there — a width fix alone was measured to change the
       sidebar case by nothing at all. `:not(:modal)` leaves the modal path (top layer, fixed,
       UA-centered) exactly as it was, and the package's own browser suite holds that.

       The height cap above belongs to the modal as well: it keeps Send above a phone's browser
       bar. Inline, the form is part of the page and the page scrolls, so a cap only turns the panel
       into a second scroll area, one a reader has to find, with Send under its edge. Here the panel
       grows with its content instead. --}}
    .visual-feedback-dialog[open]:not(:modal) {
        position: static;
        width: min(32rem, 100%);
        max-height: none;
        overflow: visible;
    }

    {{-- One focus indicator for every control in the panel. Named selectors used to cover
       three of them, which left the first and last tab stop — the close button and submit —
       on whatever ring the UA happened to draw, and left every screenshot button bare. A
       descendant rule cannot be outgrown: a control added later is covered the day it
       ships. 3px at 2px offset against --vf-bg is ≥ 3:1 in both schemes. --}}
    .visual-feedback-dialog :focus-visible {
        outline: 3px solid var(--vf-accent);
        outline-offset: 2px;
    }

    {{-- Every control in the panel is a touch target before it is anything else: 44×44 CSS px
       (WCAG 2.5.5). Naming them individually is how the screenshot buttons ended up at 21px
       tall — they were added later and nobody remembered the list. A descendant rule cannot
       be outgrown by the next control. --}}
    .visual-feedback-dialog button,
    .visual-feedback-file-input {
        min-width: 44px;
        min-height: 44px;
    }

    {{-- The heading. Nothing styled it, so it rendered as whatever the host leaves an <h2>: the
       UA's bold 1.5em in a bare document, and body text in a Tailwind host, whose preflight sets
       every heading to `font-size: inherit; font-weight: inherit`, which made it lighter than the
       bold field labels below it. The right padding keeps a long heading clear of the close
       button in the top corner of a modal panel, and it is physical because the button's `right`
       is. --}}
    .visual-feedback-heading {
        margin: 0;
        padding-right: 2.5rem;
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.3;
    }

    {{-- The screenshot editor's field labels are left out inside `:where()`, which adds nothing to
       the selector's weight. A plain `:not(.class)` would raise it above the two-class rules that
       dress particular labels further down: the privacy row would lose its flex column and, with it,
       the 44px region that ticks its box. --}}
    .visual-feedback-dialog label:where(:not(.visual-feedback-editor-field)) { display: block; margin-top: 0.75rem; font-weight: 600; }
    {{-- The list this replaced named `text` and `email` and was outgrown the same way the buttons
       above were: `tel` (the opt-in phone field) matched nothing at all, so it rendered borderless
       and at the UA's 13.333px — under the iOS threshold the comment below exists to hold, in a
       configuration this package offers and documents. Excluding the three types that must not be
       stretched to a field is a rule the next input type cannot fall out of: a checkbox and a
       radio are their own control, a file input carries its own dropzone rule below, and a hidden
       one renders nothing. The `:not()` chain lifts specificity from (0,2,1) to (0,5,1); nothing
       here depends on the old value — the file input is excluded outright, and the `textarea`
       override below is a separate selector in this list, not an `input`. The screenshot editor's
       number fields sit inside the panel in this tree and are sized by their own rule; they are
       left out inside `:where()`, so leaving them out adds no weight. --}}
    .visual-feedback-dialog input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="hidden"]):where(:not(.visual-feedback-editor-field-input)),
    .visual-feedback-dialog select,
    .visual-feedback-dialog textarea {
        width: 100%;
        margin-top: 0.25rem;
        padding: 0.5rem 0.875rem;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius-control);
        background: var(--vf-bg);
        color: var(--vf-fg);
        font: inherit;
        {{-- 16px is not a design choice, it is the iOS threshold: Safari zooms the whole page
           in when a focused field renders below it, and it does not zoom back out — the
           reporter is left on a magnified, horizontally scrolling page mid-form. `font:
           inherit` alone would drop under it on any host with a smaller root size, so the
           floor is enforced here while still following a larger host size. --}}
        font-size: max(1rem, 16px);
        {{-- 44px is the same WCAG 2.5.5 target the buttons carry. A field is a pointer target
           too, and the padding above left these at 36px — under the floor the README states
           for every control, while the touch-target measurement only ever looked at buttons.
           min-height, not height: a textarea must still grow past it. --}}
        min-height: 44px;
        box-sizing: border-box;
    }
    .visual-feedback-dialog textarea { min-height: 6rem; resize: vertical; }

    {{-- WebKit draws a native select as a menu-list button and ignores both the padding and the
       min-height above, so the category picker came out 23px tall in WebKit while Blink drew it at
       44px, under the floor this rule exists for. A fixed height is the one size WebKit honors on
       a select, and the select keeps its native arrow. Only the select: a textarea must grow. --}}
    .visual-feedback-dialog select { height: 44px; }

    {{-- The privacy acknowledgment is the one control a guest cannot submit without, and it was
       the UA default: a 13x13 box on a 20px-tall label block. Sized here rather than restyled —
       `appearance: none` would hand this package the tick, the checked, indeterminate and focus
       states, and the dark scheme that `color-scheme` above gets from the UA for free. 1.5rem
       clears the 24x24 WCAG 2.5.8 floor on its own. --}}
    .visual-feedback-dialog input[type="checkbox"] {
        flex: none;
        inline-size: 1.5rem;
        block-size: 1.5rem;
        margin: 0;
    }

    {{-- …and the 44x44 of WCAG 2.5.5 comes from the label, because a label activates its control.
       Three declarations carry that, and each is load-bearing:

         - `min-height: 44px` with `align-items: center` makes the row itself 44px tall whatever
           the host's line height is;
         - `gap: 1.25rem` beside the 1.5rem box puts the first glyph of the acknowledgment at
           44px, so the column to the left of it is a contiguous 44x44 region that ticks the box.
           That column is what the reporter actually has: the acknowledgment text is the privacy
           anchor (deliberately — the link is what makes the acknowledgment informed), and a tap
           on interactive content runs no label activation behavior, it navigates;
         - `display: flex` is what keeps that column 44px wide when the sentence wraps to three
           lines on a 320px phone, which a plain inline layout does not.

       `font-weight: 400` because the block rule above dresses field labels, and a sentence is not
       a field label. --}}
    .visual-feedback-dialog .visual-feedback-privacy {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        min-height: 44px;
        font-weight: 400;
    }

    {{-- The text of the notice, where a source hands it over: a disclosure above the checkbox, its
       summary a 44px target, the text scrolling inside a bounded box so a long notice does not push
       the submit button out of the dialog. --}}
    .visual-feedback-privacy-notice summary {
        min-height: 44px;
        display: flex;
        align-items: center;
        cursor: pointer;
        color: var(--vf-accent);
    }

    .visual-feedback-privacy-notice-text {
        max-height: 16rem;
        overflow: auto;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--vf-border, var(--vf-muted));
        border-radius: var(--vf-radius-control);
        font-size: 0.875rem;
    }

    .visual-feedback-close {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        min-width: 44px;
        min-height: 44px;
        border: 0;
        background: transparent;
        color: var(--vf-muted);
        font-size: 1.5rem;
        line-height: 1;
        cursor: pointer;
    }

    .visual-feedback-counter,
    {{-- The attachment caps. Same muted treatment as the counter — both are secondary text
       next to a field, and --vf-muted is the one tone the contrast sweep already holds at
       AA in both schemes. A new color here would be a new thing to prove. --}}
    .visual-feedback-hint,
    {{-- The capture progress line. Muted deliberately, and the choice matters as much as the
       success rule below it: `capturing` and `uploading` report progress, `attached` reports a
       result, and tinting all three would make the color mean "something is happening" and stop
       it meaning success anywhere. --}}
    .visual-feedback-capture-status { display: block; margin-top: 0.25rem; font-size: 0.8125rem; color: var(--vf-muted); }

    {{-- The two sentences that say something worked: "Screenshot attached" and the thank-you after
       a submit. As ordinary body text -- the second one in a bare <p>, the first under a class
       with no rule -- the message confirming the reporter's screenshot has arrived would be
       indistinguishable from the hint above it. --}}
    .visual-feedback-success {
        {{-- One box shape for both directions. Color plus weight and nothing else, beside an
           error alert with a margin, padding, a border, a radius and a reading-edge stripe, would
           make "screenshot attached" read as a marginal note and the failure as an alarm,
           although both are the same class of statement: the state changed, look here.

           The spacing, padding, radius and stripe are copied from the alert deliberately rather
           than approximated -- two shapes that are meant to match must be edited together, and a
           second set of values is how they drift apart again.

           No background tint here either, for the reason the alert states: --vf-success is a text
           tone proven at AA against --vf-bg, and a filled box would need a second, paler tone per
           scheme -- a second pair of colors to prove rather than reuse. --}}
        margin-top: 0.75rem;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--vf-success);
        border-inline-start: 4px solid var(--vf-success);
        border-radius: var(--vf-radius-control);
        color: var(--vf-success);
        font-weight: 600;
    }

    {{-- The glyph is what keeps the color from being the only carrier (WCAG 1.4.1). Green alone
       says "success" to everyone except the readers who need the confirmation most. It is
       aria-hidden because the sentence already says it to a screen reader, and both of these sit
       in a live region -- announcing a check mark before the sentence would be noise. --}}
    .visual-feedback-success-glyph {
        margin-inline-end: 0.375rem;
    }

    {{-- The honeypot's concealment, as a rule rather than only as an attribute.
       The markup carries both. A content security policy that allows this stylesheet through a
       nonce or hash while forbidding style attributes -- `style-src-attr 'none'`, ordinary
       hardening -- drops the attribute and keeps this, and that difference is not cosmetic: an
       exposed honeypot gets filled in by real reporters, and a honeypot hit shows the success
       screen on purpose, so the report is discarded while both sides believe it arrived.
       Off-screen rather than `display: none`, because a bot that skips undisplayed fields would
       skip the trap. --}}
    .visual-feedback-honeypot {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        left: -9999px;
    }

    .visual-feedback-dialog [role="alert"] { color: var(--vf-error); }

    {{-- Every rejection the reporter can see, in a box that reads as one.
       Owner directive: an error message is always in the red box.

       The tone alone was not enough, and the failure it produced is worth stating because it is
       not obvious from a screenshot: the rule above tints the text, so the refusal
       "you captured a screenshot but have not attached it" rendered at the same weight and
       nearly the same size as the hint "up to 5 files, 5 MB each" two lines above it. A reporter
       scanning the form read the second as advice and the first as more advice.

       Painted from a class the server sets, never from `:not(:empty)`. Every one of these
       regions is a live region that must exist before it has anything to say, so all four are in
       the markup on every render, holding the newline and indentation Blade leaves behind --
       and `:empty` does not match an element containing whitespace. A box drawn on that
       selector would be a permanently empty red rectangle under every field.

       `border-inline-start` rather than `border-left`: the widget ships seven locales today and
       the stripe has to sit at the reading edge, not at the west edge.

       No background tint. --vf-error is a text tone chosen against --vf-bg and proven at AA
       there; a filled box would need a second, paler tone per scheme, and that is a second pair
       of colors to prove rather than reuse. The stripe and the weight carry the emphasis, so
       nothing here depends on color alone (WCAG 1.4.1) -- the same argument the success glyph
       above makes. --}}
    .visual-feedback-alert--shown {
        margin-top: 0.75rem;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--vf-error);
        border-inline-start: 4px solid var(--vf-error);
        border-radius: var(--vf-radius-control);
        color: var(--vf-error);
        font-weight: 600;
    }

    {{-- The sentence that warns the browser is about to ask to share the screen. It sits between
       the capture button and the fields, and it had no rule at all -- so it rendered at body
       weight and body color, reading as a statement about the page rather than as a note about
       the button above it. Muted like the counter and the caps, which is what it is. --}}
    .visual-feedback-native-hint {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.8125rem;
        color: var(--vf-muted);
    }

    {{-- The required-field legend, muted like every other note in this form, with the star in the
       tone the controls use for theirs. Two colors on one line and only one of them is the
       point: the star has to read as the same mark the fields carry, or the legend explains a
       symbol the reporter never saw. --}}
    .visual-feedback-required-legend {
        display: block;
        margin-top: 0.75rem;
        font-size: 0.8125rem;
        color: var(--vf-muted);
    }

    {{-- What a modal shows under its heading until its fields arrive with the round trip that opens
         it: muted, like every other note of the panel, and out of the way of the fields that replace it. --}}
    .visual-feedback-loading {
        margin: 1rem 0 0;
        color: var(--vf-muted);
    }

    {{-- The host's notice above the fields. Body text rather than muted: it says who reads a report
       and who gets a copy, which a reporter has to be able to read as easily as the fields. --}}
    .visual-feedback-notice {
        margin-block: 0.25rem 0.75rem;
    }

    .visual-feedback-required-mark {
        {{-- `--vf-error`, the token this tree already paints the invalid border with, and not a
           second red of its own: the legend explains the mark the fields carry, so the two have
           to be the same tone in both schemes. It has a dark-scheme value, which a literal
           would not. --}}
        color: var(--vf-error);
    }

    {{-- The star sits after a field's label text and before the legend's, as the WireKit tree
       places them, so its spacing follows where it stands. --}}
    .visual-feedback-required-legend .visual-feedback-required-mark {
        margin-inline-end: 0.25rem;
    }

    label .visual-feedback-required-mark {
        margin-inline-start: 0.125rem;
    }

    {{-- The capture block's own separation from the field above it.

       The rhythm in this tree comes from `label { margin-top }`, which works for every group
       that starts with a label -- and the screenshot block does not: it opens with a button. So
       it sat flush against the message field, measured at 0px in a browser, which is exactly the
       "no space around the screenshot area" half of the report. Fixing it through the label rule
       was not open: there is no label to hang it on. --}}
    .visual-feedback-dialog .visual-feedback-screenshot {
        display: block;
        margin-top: 0.75rem;
    }

    {{-- The retake button that stands alone under the capture status, rather than inside the
       preview action row. This tree spaces buttons by element and attribute, and the two rules
       that do it reach `button[type="submit"]` and nothing else with a margin -- so this one sat
       flush against the green "screenshot attached" box exactly as it did in the WireKit tree.
       Reported there, present in both.

       It gets a class rather than a structural selector because the other tree already needs one
       and the two must not answer the question differently: `EveryBareCaptureButtonHasSpacing`
       derives the buttons from the rendered markup and asks each for a class with a rule, in both
       trees. A `.visual-feedback-screenshot > button` here would pass that arm only by accident
       and would also catch the capture button, which has its own spacing already. --}}
    .visual-feedback-dialog .visual-feedback-retake {
        margin-top: 0.5rem;
    }

    {{-- The two ways out of the success screen are bare siblings as well. Where their labels do
       not fit on one line, as in German on a phone, the done button wraps under "send another"
       and needs space above it, as both have in the other tree. --}}
    .visual-feedback-dialog .visual-feedback-report-another,
    .visual-feedback-dialog .visual-feedback-done {
        margin-top: 0.5rem;
    }

    {{-- The control the server marked invalid, for the reporter who can see it.
       Without this rule the error state is audible and invisible: `aria-invalid` tells a
       screen reader which field is wrong while a sighted reporter has only the shared alert
       line and no idea which of eight controls it means.
       Keyed on the attribute rather than a class, so it follows the server's verdict exactly and
       cannot drift from it -- and it therefore stays off for a rate limit or a disabled widget,
       which is the same discrimination the marking itself makes. The outline is drawn beside the
       border rather than replacing it, so a reporter who overrides --vf-border keeps both. --}}
    .visual-feedback-dialog [aria-invalid="true"] {
        border-color: var(--vf-error);
        outline: 1px solid var(--vf-error);
        outline-offset: -2px;
    }

    {{-- Announced but not shown: the character counter's settled value, so a screen reader hears
       the tally after a typing pause instead of on every keystroke, while the visible counter
       keeps updating live. `position: absolute` with no insets keeps the element at its static
       position, so it can never travel somewhere odd in a host page. --}}
    .visual-feedback-sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        border: 0;
        overflow: hidden;
        white-space: nowrap;
        clip-path: inset(50%);
    }

    {{-- The capture preview. This class had no rule anywhere in the package, and an <img> with no
       max-width lays itself out at its intrinsic size: the shipped default (scale 2, viewport
       only) makes a 640x1136 PNG on a 320px phone and a 2560x1600 one at 1280x800 — rendered at
       640 CSS px inside a 232px column, and at 2560 inside a 510px one. The panel then scrolls in
       both axes and the reporter sees about a third of the picture whose entire purpose is that
       they see what they are about to send. A Tailwind host hides this behind preflight's
       `img { max-width: 100% }`; this is the tree that has no preflight, so the rule belongs
       here. Deliberately no max-height: a cap was measured shrinking a portrait capture to
       128x227 in a 232px column, which works against the same purpose from the other side. --}}
    .visual-feedback-preview {
        display: block;
        max-width: 100%;
        height: auto;
        margin-top: 0.5rem;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius-control);
        box-sizing: border-box;
    }

    {{-- The block while a capture is waiting for a decision: attached or discarded, neither yet.

       The message naming this state lives at the end of the form, so without a mark here a
       reporter reads "you captured a screenshot but have not attached it yet" and then has to
       find what it refers to. `warning` rather than `danger`: nothing is broken, a choice is
       open -- the form submits fine from here, it sends without the image.

       It disappears by itself, because it is keyed on the state rather than set by a handler:
       attaching moves the status to `attached`, discarding resets it, and either way this
       container is no longer shown. --}}
    .visual-feedback-captured-pending {
        padding: 0.75rem;
        border: 1px solid var(--vf-warning);
        border-radius: var(--vf-radius-control);
    }

    .visual-feedback-preview-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    {{-- Attachments: a visible, focusable file input (native keyboard path) and remove
       buttons that are always visible and ≥44px (16px hover-only remove buttons are the common mistake). --}}
    .visual-feedback-file-input {
        display: block;
        width: 100%;
        margin-top: 0.25rem;
        padding: 0.5rem;
        border: 1px dashed var(--vf-border);
        border-radius: var(--vf-radius-control);
        background: var(--vf-bg);
        color: var(--vf-fg);
        font: inherit;
        box-sizing: border-box;
    }

    {{-- The button the engine draws inside the file field, in the form of the panel's other buttons. --}}
    .visual-feedback-file-input::file-selector-button {
        margin-inline-end: 0.75rem;
        padding: 0.375rem 0.875rem;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius-control);
        background: var(--vf-surface);
        color: var(--vf-fg);
        font: inherit;
        cursor: pointer;
    }
    .visual-feedback-file-list { list-style: none; margin: 0.5rem 0 0; padding: 0; }
    .visual-feedback-file {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.25rem 0;
    }
    .visual-feedback-file-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .visual-feedback-remove {
        flex: none;
        min-width: 44px;
        min-height: 44px;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius-control);
        background: var(--vf-surface);
        color: var(--vf-fg);
        font-size: 1.25rem;
        line-height: 1;
        cursor: pointer;
    }

    .visual-feedback-dialog button[type="submit"] {
        margin-top: 1rem;
        padding: 0.625rem 1.25rem;
        min-height: 44px;
        border: 0;
        border-radius: var(--vf-radius-control);
        background: var(--vf-accent);
        color: var(--vf-accent-fg);
        font: inherit;
        font-weight: 600;
        cursor: pointer;
    }

    {{-- ── The panel's secondary buttons ───────────────────────────────────────────────
       Capture, attach, discard, retake, done, report another. Every one of them was a bare
       `<button type="button">` with no rule anywhere, so each rendered in whatever chrome the
       engine's own stylesheet supplies -- and that chrome is not the same chrome.

       Measured in both engines, the capture button in the open panel:

         Blink    border rgb(118, 118, 118) on buttonface -- passes the 3:1 non-text threshold
         WebKit   border 2px outset rgb(192, 192, 192) on white -- 1.82:1, and it fails

       Dark mode is worse and it is the same cause: the engine keeps its light button surface
       while the label inherits the panel's dark-scheme foreground, so the text came back white
       on white at a ratio of exactly 1. A reader on Safari had an unreadable button and a
       reader on Chrome did not, from identical markup.

       The tokens are the panel's own, so a host that overrode them gets buttons that match:
       `--vf-border` is #7b8390 in both schemes and clears 3:1 against the light surface and the
       dark one alike, and `--vf-fg` on `--vf-bg` is the pair the panel's body text already uses.

       The close button keeps its own rule -- it is a borderless glyph in the corner, not a
       control in the flow -- and is named out rather than left to specificity. So are the screenshot
       editor's buttons, which sit inside the panel in this tree and have their own rules: left in,
       this one would paint over the color of each swatch and the accent of Apply. They are left out
       inside `:where()`, so the rule keeps the weight the buttons of the panel are overridden against. --}}
    .visual-feedback-dialog button[type="button"]:not(.visual-feedback-close):where(:not(.visual-feedback-editor-button)) {
        padding: 0.5rem 1rem;
        min-height: 44px;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius-control);
        background: var(--vf-surface);
        color: var(--vf-fg);
        font: inherit;
        cursor: pointer;
    }

    {{-- ── The report browser ──────────────────────────────────────────────────────────
       Same custom properties as the widget, so a host that already overrode --vf-accent
       gets a browser that matches without touching anything else. No new tokens: a
       second palette to keep in sync is a second palette that drifts. That holds for the
       radii too: the fields and buttons here round with the widget's --vf-radius-control,
       and only the detail pane, a panel like the widget's own, with --vf-radius. --}}
    .visual-feedback-browser {
        color: var(--vf-fg);
        font: inherit;
    }

    .visual-feedback-browser-filters {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vf-gap);
        align-items: flex-end;
        margin-bottom: var(--vf-gap);
    }

    .visual-feedback-browser-field {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .visual-feedback-browser-field > span {
        color: var(--vf-muted);
        font-size: 0.875rem;
    }

    .visual-feedback-browser-field select,
    .visual-feedback-browser-field input {
        box-sizing: border-box;
        min-height: 44px;
        padding: 0.5rem 0.875rem;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius-control);
        background: var(--vf-bg);
        color: var(--vf-fg);
        font: inherit;
        {{-- The iOS threshold the panel's fields hold for the same reason: below 16px Safari
           zooms the whole page in when a filter takes focus, and does not zoom back out. --}}
        font-size: max(1rem, 16px);
    }

    {{-- The same fixed height as the panel's select, for the same reason: WebKit ignores the
       min-height above on a native select. The date fields take it too: with the min-height alone
       and no border-box they came out 62px beside a 44px select, and the row's labels stood on two
       lines. --}}
    .visual-feedback-browser-field select,
    .visual-feedback-browser-field input { height: 44px; }
    {{-- One arrow for every select of the package, drawn rather than left to the engine: Blink set
       its own against the border. Two triangles in the muted tone, which the contrast sweep holds
       above 3:1 in both schemes, 0.875rem from the edge the text starts from on the other side. A
       forced-colors mode drops background images, so the native arrow comes back there. --}}
    .visual-feedback-dialog select,
    .visual-feedback-browser-field select {
        appearance: none;
        padding-inline-end: 2.5rem;
        background-image:
            linear-gradient(45deg, transparent 50%, var(--vf-muted) 50%),
            linear-gradient(135deg, var(--vf-muted) 50%, transparent 50%);
        background-position: calc(100% - 1.25rem) 50%, calc(100% - 0.875rem) 50%;
        background-size: 0.375rem 0.375rem;
        background-repeat: no-repeat;
    }
    .visual-feedback-dialog select:dir(rtl),
    .visual-feedback-browser-field select:dir(rtl) {
        background-position: 0.875rem 50%, 1.25rem 50%;
    }
    @media (forced-colors: active) {
        .visual-feedback-dialog select,
        .visual-feedback-browser-field select {
            appearance: auto;
            background-image: none;
            padding-inline-end: 0.875rem;
        }
    }

    {{-- The frame is the containing block of what the table positions absolutely, such as the
       hidden heading of the actions column. Without it that heading would sit at the far end of a
       table wider than the screen, outside the frame, and widen the page. --}}
    .visual-feedback-browser-scroll {
        position: relative;
        overflow-x: auto;
    }

    .visual-feedback-browser-table {
        width: 100%;
        border-collapse: collapse;
    }

    .visual-feedback-browser-table th,
    .visual-feedback-browser-table td {
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid var(--vf-border);
        text-align: left;
        {{-- Middle, not top: the row's actions are 44px buttons, and beside them the text of the other
           cells sat at the top while the buttons sat lower. --}}
        vertical-align: middle;
    }

    .visual-feedback-browser-table th {
        color: var(--vf-muted);
        font-weight: 600;
    }
    {{-- A moment stays on one line, so a date never stands above its time. --}}
    .visual-feedback-browser-when {
        white-space: nowrap;
    }

    {{-- A row's actions stand at its end, on one line, as the actions of a table row do. --}}
    .visual-feedback-browser-table .visual-feedback-browser-actions {
        text-align: end;
        white-space: nowrap;
    }

    {{-- 44px on every control in the table, the same floor the widget holds. --}}
    .visual-feedback-browser-actions button,
    .visual-feedback-browser-clear,
    .visual-feedback-browser-pager button,
    .visual-feedback-browser-detail button {
        min-height: 44px;
        padding: 0.5rem 0.875rem;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius-control);
        background: var(--vf-surface);
        color: var(--vf-fg);
        font: inherit;
        cursor: pointer;
    }

    {{-- The pager: newer reports on the left, older on the right, the position between. An end of
       the list keeps its word in place, muted, so the other button does not jump. --}}
    .visual-feedback-browser-pager {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vf-gap);
        margin-top: var(--vf-gap);
    }

    .visual-feedback-browser-pager-position,
    .visual-feedback-browser-pager-edge {
        color: var(--vf-muted);
    }

    .visual-feedback-browser-pager-edge {
        padding: 0.5rem 0.875rem;
    }

    {{-- Set like a heading, because a Tailwind host's preflight leaves every heading at the size and
       weight of body text. --}}
    .visual-feedback-browser-empty-title {
        margin: 0;
        padding: var(--vf-gap) var(--vf-gap) 0;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .visual-feedback-browser-empty {
        padding: var(--vf-gap);
        color: var(--vf-muted);
    }

    .visual-feedback-browser-error {
        padding: var(--vf-gap);
        border: 1px solid var(--vf-error);
        border-radius: var(--vf-radius-control);
        color: var(--vf-error);
    }

    .visual-feedback-browser-detail {
        margin-top: var(--vf-gap);
        padding: var(--vf-gap);
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius);
        background: var(--vf-bg);
    }

    {{-- The facts of a report as a grid, each name beside its value: in the browser's own layout every
       name took a line and its value an indented one under it. --}}
    .visual-feedback-browser-detail dl {
        display: grid;
        grid-template-columns: max-content minmax(0, 1fr);
        gap: 0.375rem 1.25rem;
        margin: 1rem 0;
    }
    .visual-feedback-browser-detail dt {
        color: var(--vf-muted);
        font-size: 0.875rem;
        font-weight: 600;
    }
    .visual-feedback-browser-detail dd {
        margin: 0;
        overflow-wrap: anywhere;
    }
    .visual-feedback-browser-detail header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--vf-gap);
    }

    {{-- A long unbroken token, a link most often, wraps inside the box rather than running out of
       it: `pre-wrap` breaks only at white space. --}}
    .visual-feedback-browser-message {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    {{-- The preview is a full-page capture and by far the heaviest thing here. The box keeps
       its space so the pane does not jump while it decodes. --}}
    .visual-feedback-browser-attachment img {
        max-width: 100%;
        height: auto;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius-control);
    }

    .visual-feedback-browser-attachment figcaption {
        color: var(--vf-muted);
        font-size: 0.8125rem;
        word-break: break-all;
    }
    {{-- ── The screenshot editor ───────────────────────────────────────────────────────
       A modal of its own over the panel, opened from the preview. The editor's bundle builds the
       picture, the crop over it and the controls into the body and positions what moves through
       CSSOM, so these rules size and paint and nothing else. No motion, as everywhere here.

       The picture keeps the editor's width so it can be read and marked, and the dialog scrolls to
       the fields and the buttons below it. --}}
    .visual-feedback-editor {
        color-scheme: var(--vf-color-scheme, light dark);
        box-sizing: border-box;
        margin: auto;
        width: min(60rem, calc(100vw - 2rem));
        max-height: calc(100vh - 2rem);
        max-height: calc(100dvh - 2rem);
        padding: 1rem;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius);
        background: var(--vf-bg);
        color: var(--vf-fg);
        overflow: auto;
    }
    .visual-feedback-editor::backdrop { background: var(--vf-backdrop); }
    .visual-feedback-editor :focus-visible {
        outline: 3px solid var(--vf-accent);
        outline-offset: 2px;
    }
    .visual-feedback-editor-title {
        margin: 0 0 0.75rem;
        font-size: 1.125rem;
        font-weight: 600;
    }
    .visual-feedback-editor-body {
        display: grid;
        gap: 0.75rem;
    }
    .visual-feedback-editor-stage {
        position: relative;
        justify-self: center;
        max-width: 100%;
        user-select: none;
        -webkit-user-select: none;
    }
    {{-- A 44px handle reaches 22px past the picture's edge. Where the pointer is a finger the picture
       keeps that much room on either side, so a picture as wide as the editor keeps its handles
       inside the editor. --}}
    @media (pointer: coarse) {
        .visual-feedback-editor-stage { max-width: calc(100% - 44px); }
    }
    {{-- The picture takes the editor's whole width and keeps it at any height of the window, and the
       dialog scrolls to the fields and buttons below it. Fitted to the height, a tall capture in a
       short window came out too small to read or mark anything in. --}}
    .visual-feedback-editor-image {
        display: block;
        max-width: 100%;
        width: auto;
        height: auto;
    }
    .visual-feedback-editor-overlay {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
    }
    .visual-feedback-editor-shade { fill: rgba(17, 24, 39, .55); }
    {{-- The crop's outline: a dark line over a light one, so it shows on a light picture as on a dark one, and
       while the crop still takes the whole picture, where there is no shade to tell its edge by. --}}
    .visual-feedback-editor-outline {
        fill: none;
        stroke: #ffffff;
        stroke-width: 4;
    }
    .visual-feedback-editor-outline-dark {
        stroke: #111827;
        stroke-width: 2;
    }
    .visual-feedback-editor-region {
        position: absolute;
        box-sizing: border-box;
        cursor: move;
        touch-action: none;
    }
    {{-- 24px handles for a mouse, 44px where the pointer is a finger (WCAG 2.5.5). Centered on the
       point by a negative margin of half their size. --}}
    .visual-feedback-editor-handle {
        position: absolute;
        box-sizing: border-box;
        width: 24px;
        height: 24px;
        margin: -12px 0 0 -12px;
        border: 2px solid var(--vf-accent);
        border-radius: 999px;
        background: #ffffff;
        touch-action: none;
    }
    @media (pointer: coarse) {
        .visual-feedback-editor-handle {
            width: 44px;
            height: 44px;
            margin: -22px 0 0 -22px;
        }
    }
    .visual-feedback-editor-handle--nw, .visual-feedback-editor-handle--se { cursor: nwse-resize; }
    .visual-feedback-editor-handle--ne, .visual-feedback-editor-handle--sw { cursor: nesw-resize; }
    .visual-feedback-editor-handle--n, .visual-feedback-editor-handle--s { cursor: ns-resize; }
    .visual-feedback-editor-handle--e, .visual-feedback-editor-handle--w { cursor: ew-resize; }
    .visual-feedback-editor-fields,
    .visual-feedback-editor-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem 0.75rem;
    }
    {{-- The toolbar's two rows. Every button stands the same distance from its neighbor, and between two groups on one line a separating line stands in the middle of a wider gap. --}}
    .visual-feedback-editor-toolbar {
        display: grid;
        gap: 0.5rem;
    }
    .visual-feedback-editor-row {
        --visual-feedback-editor-group-gap: 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem var(--visual-feedback-editor-group-gap);
    }
    .visual-feedback-editor-actions { justify-content: flex-end; }
    .visual-feedback-editor-field {
        display: grid;
        gap: 0.25rem;
        font-size: 0.875rem;
    }
    {{-- 16px, the size below which iOS zooms the page when the field takes focus. --}}
    .visual-feedback-editor-field-input {
        box-sizing: border-box;
        width: 7rem;
        min-height: 44px;
        padding: 0.5rem;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius-control);
        background: var(--vf-bg);
        color: var(--vf-fg);
        font: inherit;
        font-size: 16px;
    }
    .visual-feedback-editor-button {
        min-width: 44px;
        min-height: 44px;
        padding: 0.5rem 1rem;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius-control);
        background: var(--vf-surface);
        color: var(--vf-fg);
        font: inherit;
        cursor: pointer;
    }
    .visual-feedback-editor-button:disabled { cursor: not-allowed; opacity: .55; }
    .visual-feedback-editor-apply {
        border-color: var(--vf-accent);
        background: var(--vf-accent);
        color: var(--vf-accent-fg);
        font-weight: 600;
    }
    {{-- The tools, colors and widths are toggles, grouped, and the pressed one carries the accent. --}}
    .visual-feedback-editor-group {
        position: relative;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem 0.25rem;
    }
    {{-- The editor marks a group that begins a line, after a wrap too, and such a group carries no line. --}}
    .visual-feedback-editor-group:not(:first-child, [data-line-start])::before {
        content: '';
        position: absolute;
        inset-block: 0;
        inset-inline-start: calc(var(--visual-feedback-editor-group-gap) / -2 - 0.5px);
        border-inline-start: 1px solid var(--vf-border);
    }
    .visual-feedback-editor-button[aria-pressed="true"] {
        border-color: var(--vf-accent);
        box-shadow: inset 0 0 0 2px var(--vf-accent);
        font-weight: 600;
    }
    {{-- A color button shows its color, which the editor sets as a custom property through CSSOM. --}}
    .visual-feedback-editor-color {
        width: 44px;
        padding: 0;
        background: var(--visual-feedback-editor-swatch, transparent);
    }
    .visual-feedback-editor-color[aria-pressed="true"] {
        box-shadow: inset 0 0 0 3px var(--vf-bg), 0 0 0 2px var(--vf-accent);
    }
    {{-- In a marking tool the crop stays as it is and takes no pointer, and the marks' handles show. --}}
    .visual-feedback-editor-stage:not([data-tool="crop"]) { cursor: crosshair; }
    .visual-feedback-editor-stage:not([data-tool="crop"]) .visual-feedback-editor-region { pointer-events: none; }
    .visual-feedback-editor-stage:not([data-tool="crop"]) [data-handle] { display: none; }
    .visual-feedback-editor-stage[data-tool="crop"] .visual-feedback-editor-grips { display: none; }
    .visual-feedback-editor-targets,
    .visual-feedback-editor-grips {
        position: absolute;
        inset: 0;
        pointer-events: none;
    }
    {{-- A mark's focus target covers its box for the keyboard and takes no pointer: a press reaches
       the stroke below it, where the editor decides what was hit. --}}
    .visual-feedback-editor-target {
        position: absolute;
        box-sizing: border-box;
        min-width: 8px;
        min-height: 8px;
    }
    .visual-feedback-editor-grip {
        pointer-events: auto;
        cursor: grab;
    }
    .visual-feedback-editor-selection-light {
        stroke: #ffffff;
        stroke-width: 4;
    }
    .visual-feedback-editor-selection-dark {
        stroke: #111827;
        stroke-width: 2;
        stroke-dasharray: 6 4;
    }
