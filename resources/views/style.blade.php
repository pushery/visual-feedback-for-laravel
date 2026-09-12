{{--
    The widget's stylesheet, for BOTH view trees — framework-free, no Tailwind, no build step.
    Include it ONCE in your layout <head>, whichever tree you serve:

        @include('visual-feedback::style')

    It is not a no-op under the WireKit tree. That tree gets the smaller block in the @else
    branch at the foot of this file: the rhythm between field groups, the row under the
    screenshot preview, the box around a refusal and the concealment rule for the honeypot. A
    host who leaves the include out there loses all four, and nothing turns red.

    In the plain tree every color is a CSS custom property with a `prefers-color-scheme: dark`
    fallback, so the widget is dark-mode-neutral out of the box and a host can retint it by
    overriding the `--vf-*` properties. Publish and edit it with:

        php artisan vendor:publish --tag=visual-feedback-views
--}}
@php
    // Recorded OUTSIDE the branch below: the host wrote the include either way, and which tree
    // serves decides what this file CONTAINS, never whether it was asked for. Marking inside the
    // branch would report a WireKit host as having forgotten a line it did write.
    app(\Pushery\VisualFeedback\Support\StylesheetPresence::class)->markRendered();
@endphp
{{-- A SMALLER sheet when the WireKit tree is the one rendering, not an absent one — the @else
     branch at the foot of this file is ~185 lines. This comment used to say "nothing at all",
     which was true when it was written and stopped being true when that branch was added; the
     line count is the check, not the sentence. Most of what the plain tree needs IS dead weight
     against the application's own design tokens, and would fight them — but tokens position no
     floating panel, style no dialog and conceal no honeypot.

     The guard is here rather than in the host's layout because `ui.variant` defaults to `auto`:
     installing WireKit now switches the tree WITHOUT the host touching their layout, so an
     @include they wrote once would otherwise start shipping CSS for a tree that is no longer
     being served.

     It asks which tree RESOLVES, not which one is configured, and those are different after the
     documented umbrella publish: that tag copies the plain templates into the host's
     resources/views/vendor, Laravel puts that path first, and the plain tree then serves while
     `ui.variant` still says wirekit. Reading the config answer there silenced this stylesheet
     over a plain widget — no positioning, no dialog styling, and no concealment rule for the
     honeypot, which lives in here. --}}
@if (! app(\Pushery\VisualFeedback\Support\ServedViewTree::class)->servingWireKit())
<style>
    :root {
        --vf-bg: #ffffff;
        --vf-fg: #111827;
        --vf-muted: #6b7280;
        {{-- One border value for BOTH schemes: 3.82:1 on the light surface and 3.84:1 on the
           dark one, so the 1px boundary of an input, the file dropzone and a remove button
           clears the 3:1 WCAG 1.4.11 floor either way. The conventional light gray (#d1d5db)
           measured 1.47:1 — a boundary nobody with low vision can find. Because one value
           carries both schemes, the dark block below does NOT override it. --}}
        --vf-border: #7b8390;
        --vf-accent: #2563eb;
        --vf-accent-fg: #ffffff;
        --vf-error: #b91c1c;
        {{-- The one tone that says a thing WORKED. Measured on the surface it sits on rather than
           picked: 5.02:1 on #ffffff and 8.42:1 on the dark #1f2937, so both clear the 4.5:1 AA
           floor for body text. Per-scheme like --vf-error and unlike --vf-border, because a
           single green cannot carry both. --}}
        --vf-success: #15803d;
        --vf-backdrop: rgba(17, 24, 39, .5);
        --vf-radius: 10px;
        --vf-fab-size: 3.5rem;
        --vf-gap: 1rem;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --vf-bg: #1f2937;
            --vf-fg: #f9fafb;
            --vf-muted: #9ca3af;
            {{-- The accent carries two jobs that pull against each other on the dark surface:
               it is the FILL under a white label (needs 4.5:1 against #ffffff) and it is the
               focus ring and the button boundary (needs 3:1 against --vf-bg). The window
               between those is narrow — #2f6fe4 sits in it at 4.65:1 and 3.16:1. The lighter
               blue this used to be (#3b82f6) rendered the white label at 3.68:1. --}}
            --vf-accent: #2f6fe4;
            --vf-accent-fg: #ffffff;
            --vf-error: #f87171;
            --vf-success: #4ade80;
            --vf-backdrop: rgba(0, 0, 0, .6);
        }
    }

    {{-- x-cloak gotcha: without this rule an [x-cloak] element flashes before
       Alpine boots. Shipping the rule with the stylesheet keeps it never-undefined. --}}
    [x-cloak] { display: none !important; }

    {{-- Single-action FAB: fixed, ≥ 44px AAA target, composes the iOS safe-area insets. --}}
    .visual-feedback-fab {
        {{-- Tell the UA which scheme this surface is painted in. The tokens above flip
           themselves dark, but anything the BROWSER draws — link color, the native "Choose
           file" button, the checkbox, the select popup, the default focus ring — stays in
           light-scheme colors unless it is told. Untold, the privacy link rendered at
           1.56:1 on the dark dialog. It is set on the package's own surfaces, never on
           :root: a library must not repaint the host application's form controls. --}}
        color-scheme: light dark;
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

    .visual-feedback-fab--bottom-right { bottom: calc(var(--vf-gap) + env(safe-area-inset-bottom, 0px)); right: calc(var(--vf-gap) + env(safe-area-inset-right, 0px)); }
    .visual-feedback-fab--bottom-left  { bottom: calc(var(--vf-gap) + env(safe-area-inset-bottom, 0px)); left: calc(var(--vf-gap) + env(safe-area-inset-left, 0px)); }
    .visual-feedback-fab--top-right    { top: calc(var(--vf-gap) + env(safe-area-inset-top, 0px)); right: calc(var(--vf-gap) + env(safe-area-inset-right, 0px)); }
    .visual-feedback-fab--top-left     { top: calc(var(--vf-gap) + env(safe-area-inset-top, 0px)); left: calc(var(--vf-gap) + env(safe-area-inset-left, 0px)); }

    {{-- Native <dialog>: the browser gives us the top layer, focus trap, Esc-to-close and
       focus return to the trigger for free — we only style the surface. --}}
    .visual-feedback-dialog {
        color-scheme: light dark;   {{-- see the FAB rule above — it inherits to every control inside --}}
        {{-- border-box, or the width below is only the CONTENT: the 1.5rem padding and the 1px
           border are then added on top and the panel is 50px wider than it says. On a 320px
           phone that is the difference between fitting and the page scrolling sideways. --}}
        box-sizing: border-box;
        {{-- The UA centers a modal <dialog> with `margin: auto`, and Tailwind's preflight
           (`*, ::after, ::before, ::backdrop { margin: 0 }`) takes it away — so in a Tailwind
           host, which is most Laravel apps, the panel lands in the top-left CORNER. Measured
           in a real WireKit app: top/left 0 instead of 130/384 at 1280x900. Restated here for
           the same reason box-sizing, padding and border above are restated: this stylesheet
           may not assume it is the last word on the element. --}}
        margin: auto;
        width: min(32rem, calc(100vw - 2rem));
        {{-- dvh, not vh. On a phone `vh` is the viewport with the browser UI RETRACTED, so a
           dialog sized in vh is taller than the space actually on screen while the URL bar is
           showing — its submit button sits under the chrome and the reporter cannot reach it.
           The vh line stays as the fallback for engines without dvh. --}}
        max-height: min(80vh, 40rem);
        max-height: min(80dvh, 40rem);
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

         - it contributes NOTHING to its container's height, so whatever the host puts after the
           form is overlaid by it;
         - with no positioned ancestor it resolves against the INITIAL containing block, so the
           UA's `inset-inline: 0` plus the `margin: auto` restated above center it in the
           VIEWPORT — measured 632px to the left of its own 264px sidebar at a 1280px viewport,
           i.e. across the main column, not merely overhanging its own;
         - only then does `100vw` in the width above track the viewport instead of the column.

       `position: static` is the one declaration that answers all three, and `min(32rem, 100%)`
       only means "the column" once it is there — a width fix alone was measured to change the
       sidebar case by nothing at all. `:not(:modal)` leaves the modal path (top layer, fixed,
       UA-centered) exactly as it was, and the package's own browser suite holds that. --}}
    .visual-feedback-dialog[open]:not(:modal) {
        position: static;
        width: min(32rem, 100%);
    }

    {{-- ONE focus indicator for every control in the panel. Named selectors used to cover
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

    .visual-feedback-dialog label { display: block; margin-top: 0.75rem; font-weight: 600; }
    {{-- The list this replaced named `text` and `email` and was outgrown the same way the buttons
       above were: `tel` (the opt-in phone field) matched nothing at all, so it rendered borderless
       and at the UA's 13.333px — under the iOS threshold the comment below exists to hold, in a
       configuration this package offers and documents. Excluding the three types that must NOT be
       stretched to a field is a rule the next input type cannot fall out of: a checkbox and a
       radio are their own control, a file input carries its own dropzone rule below, and a hidden
       one renders nothing. The `:not()` chain lifts specificity from (0,2,1) to (0,5,1); nothing
       here depends on the old value — the file input is excluded outright, and the `textarea`
       override below is a separate selector in this list, not an `input`. --}}
    .visual-feedback-dialog input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="hidden"]),
    .visual-feedback-dialog select,
    .visual-feedback-dialog textarea {
        width: 100%;
        margin-top: 0.25rem;
        padding: 0.5rem 0.625rem;
        border: 1px solid var(--vf-border);
        border-radius: 6px;
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

    {{-- The privacy acknowledgment is the one control a guest cannot submit without, and it was
       the UA default: a 13x13 box on a 20px-tall label block. Sized here rather than restyled —
       `appearance: none` would hand this package the tick, the checked, indeterminate and focus
       states, and the dark scheme that `color-scheme: light dark` above currently gets from the
       UA for free. 1.5rem clears the 24x24 WCAG 2.5.8 floor on its own. --}}
    .visual-feedback-dialog input[type="checkbox"] {
        flex: none;
        inline-size: 1.5rem;
        block-size: 1.5rem;
        margin: 0;
    }

    {{-- …and the 44x44 of WCAG 2.5.5 comes from the LABEL, because a label activates its control.
       Three declarations carry that, and each is load-bearing:

         - `min-height: 44px` with `align-items: center` makes the row itself 44px tall whatever
           the host's line height is;
         - `gap: 1.25rem` beside the 1.5rem box puts the first glyph of the acknowledgment at
           44px, so the column to the LEFT of it is a contiguous 44x44 region that ticks the box.
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
    {{-- The capture progress line. Muted DELIBERATELY, and the choice matters as much as the
       success rule below it: `capturing` and `uploading` report progress, `attached` reports a
       result, and tinting all three would make the color mean "something is happening" and stop
       it meaning success anywhere. --}}
    .visual-feedback-capture-status { display: block; margin-top: 0.25rem; font-size: 0.8125rem; color: var(--vf-muted); }

    {{-- The two sentences that say something WORKED: "Screenshot attached" and the thank-you after
       a submit. Both rendered as ordinary body text -- the second one in a bare <p>, the first
       under a class with no rule in either tree -- so the message indistinguishable from the hint
       above it was the one confirming the reporter's screenshot had arrived. --}}
    .visual-feedback-success {
        {{-- ONE BOX SHAPE FOR BOTH DIRECTIONS, and the asymmetry it replaces was the finding.
           This rule used to be color plus weight and nothing else, while the error alert below
           carried a margin, padding, a border, a radius and a reading-edge stripe. On the screen
           that made "screenshot attached" read as a marginal note and the failure as an alarm,
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
        border-radius: 6px;
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

    {{-- The honeypot's concealment, as a RULE rather than only as an attribute.
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

       Painted from a CLASS the server sets, never from `:not(:empty)`. Every one of these
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
        border-radius: 6px;
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
       point: the star has to read as the SAME mark the fields carry, or the legend explains a
       symbol the reporter never saw. --}}
    .visual-feedback-required-legend {
        display: block;
        margin-top: 0.75rem;
        font-size: 0.8125rem;
        color: var(--vf-muted);
    }

    .visual-feedback-required-mark {
        {{-- `--vf-error`, the token this tree already paints the invalid border with, and NOT a
           second red of its own: the legend explains the mark the fields carry, so the two have
           to be the same tone in both schemes. It has a dark-scheme value, which a literal
           would not. --}}
        color: var(--vf-error);
        margin-inline-end: 0.25rem;
    }

    {{-- The capture block's own separation from the field above it.

       The rhythm in this tree comes from `label { margin-top }`, which works for every group
       that STARTS with a label -- and the screenshot block does not: it opens with a button. So
       it sat flush against the message field, measured at 0px in a browser, which is exactly the
       "no space around the screenshot area" half of the report. Fixing it through the label rule
       was not open: there is no label to hang it on. --}}
    .visual-feedback-dialog .visual-feedback-screenshot {
        display: block;
        margin-top: 0.75rem;
    }

    {{-- The control the server marked invalid, for the reporter who can see it.
       Until this rule existed the error state was audible and invisible: `aria-invalid` told a
       screen reader which field was wrong while a sighted reporter had only the shared alert
       line and no idea which of eight controls it meant.
       Keyed on the ATTRIBUTE rather than a class, so it follows the server's verdict exactly and
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
       max-width lays itself out at its INTRINSIC size: the shipped default (scale 2, viewport
       only) makes a 640x1136 PNG on a 320px phone and a 2560x1600 one at 1280x800 — rendered at
       640 CSS px inside a 232px column, and at 2560 inside a 510px one. The panel then scrolls in
       both axes and the reporter sees about a third of the picture whose entire purpose is that
       they see what they are about to send. A Tailwind host hides this behind preflight's
       `img { max-width: 100% }`; this is the tree that has no preflight, so the rule belongs
       here. Deliberately NO max-height: a cap was measured shrinking a portrait capture to
       128x227 in a 232px column, which works against the same purpose from the other side. --}}
    .visual-feedback-preview {
        display: block;
        max-width: 100%;
        height: auto;
        margin-top: 0.5rem;
        border: 1px solid var(--vf-border);
        border-radius: 6px;
        box-sizing: border-box;
    }

    {{-- The block while a capture is waiting for a decision: attached or discarded, neither yet.

       The message naming this state lives at the end of the form, so without a mark here a
       reporter reads "you captured a screenshot but have not attached it yet" and then has to
       find what it refers to. `warning` rather than `danger`: nothing is broken, a choice is
       open -- the form submits fine from here, it simply sends without the image.

       It disappears by itself, because it is keyed on the state rather than set by a handler:
       attaching moves the status to `attached`, discarding resets it, and either way this
       container is no longer shown. --}}
    .visual-feedback-captured-pending {
        padding: var(--space-wk-md, 1rem);
        border: 1px solid var(--color-wk-warning, #b45309);
        border-radius: var(--radius-wk-md, 0.5rem);
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
        border-radius: 6px;
        background: var(--vf-bg);
        color: var(--vf-fg);
        font: inherit;
        box-sizing: border-box;
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
        border-radius: 6px;
        background: transparent;
        color: var(--vf-muted);
        font-size: 1.25rem;
        line-height: 1;
        cursor: pointer;
    }

    .visual-feedback-dialog button[type="submit"] {
        margin-top: 1rem;
        padding: 0.625rem 1.25rem;
        min-height: 44px;
        border: 0;
        border-radius: 6px;
        background: var(--vf-accent);
        color: var(--vf-accent-fg);
        font: inherit;
        font-weight: 600;
        cursor: pointer;
    }

    {{-- ── The report browser ──────────────────────────────────────────────────────────
       Same custom properties as the widget, so a host that already overrode --vf-accent
       gets a browser that matches without touching anything else. No new tokens: a
       second palette to keep in sync is a second palette that drifts. --}}
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
        min-height: 44px;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius);
        background: var(--vf-bg);
        color: var(--vf-fg);
        font: inherit;
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
        vertical-align: top;
    }

    .visual-feedback-browser-table th {
        color: var(--vf-muted);
        font-weight: 600;
    }

    {{-- 44px on every control in the table, the same floor the widget holds. --}}
    .visual-feedback-browser-actions button,
    .visual-feedback-browser-clear,
    .visual-feedback-browser-detail button {
        min-height: 44px;
        padding: 0.5rem 0.875rem;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius);
        background: var(--vf-bg);
        color: var(--vf-fg);
        font: inherit;
        cursor: pointer;
    }

    .visual-feedback-browser-empty {
        padding: var(--vf-gap);
        color: var(--vf-muted);
    }

    .visual-feedback-browser-detail {
        margin-top: var(--vf-gap);
        padding: var(--vf-gap);
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius);
        background: var(--vf-bg);
    }

    .visual-feedback-browser-detail header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--vf-gap);
    }

    .visual-feedback-browser-message {
        white-space: pre-wrap;
    }

    {{-- The preview is a full-page capture and by far the heaviest thing here. The box keeps
       its space so the pane does not jump while it decodes. --}}
    .visual-feedback-browser-attachment img {
        max-width: 100%;
        height: auto;
        border: 1px solid var(--vf-border);
        border-radius: var(--vf-radius);
    }

    .visual-feedback-browser-attachment figcaption {
        color: var(--vf-muted);
        font-size: 0.8125rem;
        word-break: break-all;
    }
</style>
@else
{{-- The WireKit tree gets LAYOUT only, and that is the whole difference from the block above.

     What the guard is for is COLOR and design: a second set of --vf-* colors, borders and radii
     would fight the application's own tokens, and that is why this stylesheet silences itself
     for that tree. It does not follow that the tree needs no CSS at all, and for four releases
     it read as though it did. The WireKit tree emits the SAME class names -- six of them carry a
     rule here -- so `.visual-feedback-preview-actions` lost `display: flex` and its gap, and the
     Attach / Discard / Retake row rendered with no space between the buttons at all. A consumer
     photographed four such places and filed it.

     Every value below is a WireKit token with the plain tree's own number as its fallback, so a
     host who tunes their spacing scale takes this along and one who never loaded WireKit's CSS
     still gets the spacing rather than none.

     AND THE HONEYPOT RULE IS HERE NOW. Both trees hide that field two ways -- an inline
     `style` attribute and this class -- because a policy that forbids style ATTRIBUTES
     (`style-src-attr 'none'`, which any `style-src` without an attribute clause implies) drops
     the first one. On the plain tree the class caught it; on the WireKit tree there was nothing
     to catch it, and the documentation said so and told the host to write the rule themselves.
     A visible honeypot is filled in by real people, and a honeypot hit is answered with the
     success screen on purpose -- so their report is thanked for and discarded. That is the one
     failure here worth more than a margin. --}}
<style>
    {{-- Concealment, not decoration. See the note above. --}}
    .visual-feedback-honeypot {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        left: -9999px;
    }

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

    {{-- The row under the preview. Without `display: flex` the three buttons are inline boxes
       with a word space between them, which is why this one reads as broken rather than tight. --}}
    {{-- The row under the preview. Without `display: flex` the three buttons are inline boxes
       with a word space between them, which is why this one reads as broken rather than tight.

       The step above it is the FORM's step, not a smaller one of its own. The block used to run
       0.75rem / 0.5rem / 0.5rem over its three parts while every direct child of the form got
       1rem, and the button row was the one that read as cramped -- it sits between two things
       that are spaced a third wider than it is. --}}
    .visual-feedback-preview-actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--gap-wk-sm, 0.5rem);
        margin-block-start: var(--space-wk-md, 1rem);
    }

    {{-- The preview speaks the dropzone's language: dashed, rounded, with room around it. It sat
       next to WireKit's own file-upload area wearing a thin solid hairline and no padding, so two
       controls that do the same job -- "here is the file you picked" -- looked unrelated.

       The height cap is the other half. Unbounded, a desktop capture is taller than the rest of
       the form put together and pushes the buttons below the fold; `contain` keeps the aspect
       ratio while it shrinks, so the preview still shows what was captured rather than a crop. --}}
    .visual-feedback-preview {
        display: block;
        max-width: 100%;
        height: auto;
        padding: var(--space-wk-sm, 0.5rem);
        border: 1px dashed var(--color-wk-border, #7b8390);
        border-radius: var(--radius-wk-md, 0.5rem);
        margin-block-start: var(--space-wk-md, 1rem);
    }

    {{-- The height cap, and the media query around it is the whole point.

       An unconditional `max-height` was proposed once and rejected on a measurement: on an iPhone
       SE profile it shrank a PORTRAIT capture to 128x227 inside a 232px column -- 55% of the space
       it had -- because capping the height of a `contain` image pulls its width along. That is the
       worst place to lose it: this preview is the step where a reporter sees what they are about
       to send.

       Above 480px the column is wide enough that the cap takes height without taking width, and
       there the unbounded preview was the problem: a desktop capture is taller than the rest of
       the form together and pushes the buttons below the fold. So the cap applies exactly where
       it helps, and the narrow case keeps the behavior that measurement earned. --}}
    @media (min-width: 480px) {
        .visual-feedback-preview {
            max-height: 240px;
            object-fit: contain;
        }
    }

    {{-- Secondary text under a field: the character counter, the attachment caps, and the capture
       progress line. Muted deliberately -- see the plain block for why the success tone must not
       reach `capturing` and `uploading`. --}}
    .visual-feedback-counter,
    .visual-feedback-hint,
    .visual-feedback-capture-status {
        display: block;
        margin-block-start: var(--space-wk-xs, 0.25rem);
        color: var(--color-wk-text-muted, #6b7280);
    }

    {{-- THE BOX IS THE KIT'S NOW, and what is left here is only the gap above it.

       This rule used to paint a full success box of its own -- border, left rule, radius, tone,
       weight -- built to match the alert "from the same token scale". Matching by hand is exactly
       the drift the alert component exists to prevent, and it showed: the two were a token release
       apart, so the success box and the danger box beside it stopped being the same shape.

       The appearance comes from <x-wirekit::alert intent="success">, and these declarations are
       the FALLBACK beneath it, in `:where()` so they carry zero specificity and lose to the kit
       wherever its utilities are compiled. A host without a Tailwind build -- the case this
       stylesheet exists for -- would otherwise get a confirmation with no box at all. The glyph
       rule is gone with the glyph: the kit draws its own icon and the package no longer paints a
       second one beside it. --}}
    :where(.visual-feedback-success) {
        margin-block-start: var(--space-wk-sm, 0.5rem);
        padding: var(--space-wk-sm, 0.5rem) var(--space-wk-md, 0.75rem);
        border: 1px solid var(--color-wk-border-success, #15803d);
        border-inline-start: 4px solid var(--color-wk-border-success, #15803d);
        border-radius: var(--radius-wk-md, 0.5rem);
        color: var(--color-wk-success-text, #15803d);
        font-weight: var(--font-wk-heading-weight, 600);
    }

    {{-- The three buttons that are bare siblings of a paragraph or a link, and therefore had no
       selector at all -- not even one a host could have hung their own rule on. --}}
    .visual-feedback-capture,
    .visual-feedback-submit,
    .visual-feedback-report-another {
        margin-block-start: var(--space-wk-sm, 0.5rem);
    }

    {{-- ── Vertical rhythm ─────────────────────────────────────────────────────────────
       The gap between one field group and the next. The plain tree has carried this since it
       existed, as `label { margin-top: 0.75rem }`; this tree renders WireKit components instead
       of labels of its own, and so had NO rule for it -- every group sat flush against the one
       above, and a label read as the caption of the field before it rather than of the field
       after it. That is why the reported symptom is about grouping and not about tightness:
       with no space anywhere, the eye pairs each label with the wrong control.

       The owl selector rather than `gap`, and the reason is that this element is a <form> whose
       layout nobody chose: switching it to flex to reach `gap` would re-parent every child into
       a flex context -- which changes how the honeypot, the challenge slot a host injects, and
       WireKit's own file-upload behave. `* + *` adds spacing and changes no layout model.

       It resolves the three buttons above rather than fighting them: same property, higher
       specificity, so a direct child of the form takes this value and the ones nested deeper
       (the capture button, which is inside `.visual-feedback-screenshot`) keep theirs. --}}
    .visual-feedback-panel form > * + * {
        margin-block-start: var(--space-wk-md, 1rem);
    }

    {{-- ...and the two children that must not COUNT in that rhythm, because they take no room.

       Measured in the live dialog: the honeypot is 1px tall and the challenge slot is 0px while
       no provider renders into it, and each still collected a full step. With no guest fields on
       screen that put two steps plus the panel padding between the header and the first field --
       the gap the report is about.

       `:empty` is not enough for the honeypot: it HAS children, it is just positioned out of the
       flow, so it is named directly. The challenge slot is the opposite case -- an ordinary block
       that is genuinely empty until a host injects something -- and `:empty` is exactly the
       question there, so its spacing returns by itself the moment it has content.

       The margin is removed from the element AFTER them as well: the owl selector spaces a child
       from its predecessor, so skipping a zero-height predecessor means the next visible element
       must not inherit a step from it either. That is what the second selector does. --}}
    .visual-feedback-panel form > .visual-feedback-honeypot,
    .visual-feedback-panel form > .visual-feedback-challenge:empty {
        margin-block-start: 0;
    }

    .visual-feedback-panel form > .visual-feedback-honeypot + *,
    .visual-feedback-panel form > .visual-feedback-challenge:empty + * {
        margin-block-start: 0;
    }

    {{-- …and the one child the rule above cannot reach on its own. The privacy anchor is a direct
       child of the form and an INLINE box, and a vertical margin on an inline box does nothing —
       so the submit button, which WireKit renders `inline-flex`, sat on the same line as the
       link and overlapped it by 30px, measured in a browser at a phone width.

       `display: block` is the whole fix: it makes the anchor a block box, which both takes the
       margin above and puts the button back on its own line. Width is left alone, so the link's
       clickable area still ends with its text rather than spanning the panel — an anchor that
       reaches the full width invites a click on empty space beside the words. --}}
    .visual-feedback-panel form > a {
        display: block;
    }

    {{-- Every rejection the reporter can see, in a box that reads as one.
       Owner directive: an error message is always in the red box.

       This tree had no error styling AT ALL -- not even the tint the plain tree gives every
       `[role="alert"]` -- so "something went wrong, please try again" rendered in body color at
       body weight, indistinguishable from the attachment caps a few lines above it.

       Painted from a CLASS the server sets, never from `:not(:empty)`: all four alert regions
       are live regions that must be in the markup before they have anything to say, and they
       therefore always contain Blade's leftover whitespace, which `:empty` does not match.

       WireKit's own danger tokens, not a palette of ours -- `--color-wk-danger-text` is the
       tone that design system already proves against its surfaces, and inventing a second red
       here is how a widget stops looking like the app it is embedded in. The fallbacks are the
       plain tree's own error tone, so a host that loads this tree without WireKit's stylesheet
       still gets a red box rather than an unpainted one. --}}
    {{-- THE BOX IS THE KIT'S, and this is the FALLBACK underneath it -- which is why it is wrapped
       in `:where()`.

       The first attempt deleted these declarations outright, on the reasoning that a hand copy of
       the alert component's shape drifts from it. True, and it broke something the browser suite
       caught: WireKit's own box is Tailwind utilities that the CONSUMING APP compiles. A host
       without a Tailwind build -- the case this stylesheet exists for, and the one the demo
       reproduces -- got a refusal with no border at all.

       `:where()` has zero specificity, so every one of these loses to the kit's utilities wherever
       they are compiled, and applies where they are not. That is the honest shape of a fallback:
       present, and never in the way. --}}
    :where(.visual-feedback-alert--shown) {
        margin-block-start: var(--space-wk-sm, 0.5rem);
        padding: var(--space-wk-sm, 0.5rem) var(--space-wk-md, 0.75rem);
        border: 1px solid var(--color-wk-border-error, #b91c1c);
        border-inline-start: 4px solid var(--color-wk-border-error, #b91c1c);
        border-radius: var(--radius-wk-md, 0.5rem);
        color: var(--color-wk-danger-text, #b91c1c);
        font-weight: var(--font-wk-heading-weight, 600);
    }

    {{-- The "your browser will ask to share your screen" note, muted like the counter and the
       caps beside it -- it describes the button above it rather than the page. --}}
    .visual-feedback-native-hint {
        display: block;
        margin-block-start: var(--space-wk-xs, 0.25rem);
        font-size: var(--text-wk-sm, 0.8125rem);
        color: var(--color-wk-text-muted, #6b7280);
    }

    {{-- ── The trigger's two offsets, made equal ───────────────────────────────────────
       WireKit places its FAB with a DIFFERENT token per axis: `.wk-fab` sets
       `inset-block-end` from `--padding-wk-y-lg` while the position class sets the inline edge
       from `--padding-wk-x-lg`. Measured in the installed stylesheet, not inferred: y is
       .75rem and x is 1rem, so the button sits 16px from the side and 12px from the bottom.
       Reported from a consumer as the button looking pushed against the side rather than
       resting in the corner, and that is what an unequal inset looks like once you see it.

       So this restates the block axis from the INLINE token -- the two axes then move together
       and follow whatever a host has themed `--padding-wk-x-lg` to, which is the property that
       matters more than the specific number. The safe-area term is kept exactly as WireKit
       composes it; dropping it would put the button under the home indicator on a phone.

       This is a WORKAROUND for an upstream defect and is reported there. Delete this rule the
       day the component positions both axes from one token; the guard that pins it says how to
       check. --}}
    .visual-feedback-fab.wk-fab {
        inset-block-end: calc(var(--padding-wk-x-lg, 1rem) + env(safe-area-inset-bottom, 0px));
    }
</style>
@endif
