{{--
    Plain-tree stylesheet — framework-free, no Tailwind, no build step. Include it ONCE
    in your layout <head>:

        @include('visual-feedback::style')

    Every color is a CSS custom property with a `prefers-color-scheme: dark` fallback, so
    the widget is dark-mode-neutral out of the box and a host can retint it by overriding
    the `--vf-*` properties. Publish and edit it with:

        php artisan vendor:publish --tag=visual-feedback-views
--}}
{{-- Nothing at all when the WireKit tree is the one rendering. That tree is styled by the
     application's own design tokens, so this stylesheet would be dead weight at best and would
     fight it at worst.

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
        /* One border value for BOTH schemes: 3.82:1 on the light surface and 3.84:1 on the
           dark one, so the 1px boundary of an input, the file dropzone and a remove button
           clears the 3:1 WCAG 1.4.11 floor either way. The conventional light gray (#d1d5db)
           measured 1.47:1 — a boundary nobody with low vision can find. Because one value
           carries both schemes, the dark block below does NOT override it. */
        --vf-border: #7b8390;
        --vf-accent: #2563eb;
        --vf-accent-fg: #ffffff;
        --vf-error: #b91c1c;
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
            /* The accent carries two jobs that pull against each other on the dark surface:
               it is the FILL under a white label (needs 4.5:1 against #ffffff) and it is the
               focus ring and the button boundary (needs 3:1 against --vf-bg). The window
               between those is narrow — #2f6fe4 sits in it at 4.65:1 and 3.16:1. The lighter
               blue this used to be (#3b82f6) rendered the white label at 3.68:1. */
            --vf-accent: #2f6fe4;
            --vf-accent-fg: #ffffff;
            --vf-error: #f87171;
            --vf-backdrop: rgba(0, 0, 0, .6);
        }
    }

    /* x-cloak gotcha: without this rule an [x-cloak] element flashes before
       Alpine boots. Shipping the rule with the stylesheet keeps it never-undefined. */
    [x-cloak] { display: none !important; }

    /* Single-action FAB: fixed, ≥ 44px AAA target, composes the iOS safe-area insets. */
    .visual-feedback-fab {
        /* Tell the UA which scheme this surface is painted in. The tokens above flip
           themselves dark, but anything the BROWSER draws — link color, the native "Choose
           file" button, the checkbox, the select popup, the default focus ring — stays in
           light-scheme colors unless it is told. Untold, the privacy link rendered at
           1.56:1 on the dark dialog. It is set on the package's own surfaces, never on
           :root: a library must not repaint the host application's form controls. */
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
        /* A pill button is one line. It inherits the host's font, so a wider face or a
           longer translation ("Feedback senden") wraps it into a two-line lump that no
           longer reads as a button — seen for real in a consumer app, where the capture's
           font metrics differed just enough to break it. */
        white-space: nowrap;
        cursor: pointer;
        box-shadow: 0 6px 20px rgba(0, 0, 0, .18);
    }
    .visual-feedback-fab:focus-visible { outline: 3px solid var(--vf-accent); outline-offset: 2px; }

    .visual-feedback-fab--bottom-right { bottom: calc(var(--vf-gap) + env(safe-area-inset-bottom, 0px)); right: calc(var(--vf-gap) + env(safe-area-inset-right, 0px)); }
    .visual-feedback-fab--bottom-left  { bottom: calc(var(--vf-gap) + env(safe-area-inset-bottom, 0px)); left: calc(var(--vf-gap) + env(safe-area-inset-left, 0px)); }
    .visual-feedback-fab--top-right    { top: calc(var(--vf-gap) + env(safe-area-inset-top, 0px)); right: calc(var(--vf-gap) + env(safe-area-inset-right, 0px)); }
    .visual-feedback-fab--top-left     { top: calc(var(--vf-gap) + env(safe-area-inset-top, 0px)); left: calc(var(--vf-gap) + env(safe-area-inset-left, 0px)); }

    /* Native <dialog>: the browser gives us the top layer, focus trap, Esc-to-close and
       focus return to the trigger for free — we only style the surface. */
    .visual-feedback-dialog {
        color-scheme: light dark;   /* see the FAB rule above — it inherits to every control inside */
        /* border-box, or the width below is only the CONTENT: the 1.5rem padding and the 1px
           border are then added on top and the panel is 50px wider than it says. On a 320px
           phone that is the difference between fitting and the page scrolling sideways. */
        box-sizing: border-box;
        /* The UA centers a modal <dialog> with `margin: auto`, and Tailwind's preflight
           (`*, ::after, ::before, ::backdrop { margin: 0 }`) takes it away — so in a Tailwind
           host, which is most Laravel apps, the panel lands in the top-left CORNER. Measured
           in a real WireKit app: top/left 0 instead of 130/384 at 1280x900. Restated here for
           the same reason box-sizing, padding and border above are restated: this stylesheet
           may not assume it is the last word on the element. */
        margin: auto;
        width: min(32rem, calc(100vw - 2rem));
        /* dvh, not vh. On a phone `vh` is the viewport with the browser UI RETRACTED, so a
           dialog sized in vh is taller than the space actually on screen while the URL bar is
           showing — its submit button sits under the chrome and the reporter cannot reach it.
           The vh line stays as the fallback for engines without dvh. */
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

    /* Inline mode shares this element — the template only adds `open` — and that is the whole
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
       UA-centered) exactly as it was, and the package's own browser suite holds that. */
    .visual-feedback-dialog[open]:not(:modal) {
        position: static;
        width: min(32rem, 100%);
    }

    /* ONE focus indicator for every control in the panel. Named selectors used to cover
       three of them, which left the first and last tab stop — the close button and submit —
       on whatever ring the UA happened to draw, and left every screenshot button bare. A
       descendant rule cannot be outgrown: a control added later is covered the day it
       ships. 3px at 2px offset against --vf-bg is ≥ 3:1 in both schemes. */
    .visual-feedback-dialog :focus-visible {
        outline: 3px solid var(--vf-accent);
        outline-offset: 2px;
    }

    /* Every control in the panel is a touch target before it is anything else: 44×44 CSS px
       (WCAG 2.5.5). Naming them individually is how the screenshot buttons ended up at 21px
       tall — they were added later and nobody remembered the list. A descendant rule cannot
       be outgrown by the next control. */
    .visual-feedback-dialog button,
    .visual-feedback-file-input {
        min-width: 44px;
        min-height: 44px;
    }

    .visual-feedback-dialog label { display: block; margin-top: 0.75rem; font-weight: 600; }
    /* The list this replaced named `text` and `email` and was outgrown the same way the buttons
       above were: `tel` (the opt-in phone field) matched nothing at all, so it rendered borderless
       and at the UA's 13.333px — under the iOS threshold the comment below exists to hold, in a
       configuration this package offers and documents. Excluding the three types that must NOT be
       stretched to a field is a rule the next input type cannot fall out of: a checkbox and a
       radio are their own control, a file input carries its own dropzone rule below, and a hidden
       one renders nothing. The `:not()` chain lifts specificity from (0,2,1) to (0,5,1); nothing
       here depends on the old value — the file input is excluded outright, and the `textarea`
       override below is a separate selector in this list, not an `input`. */
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
        /* 16px is not a design choice, it is the iOS threshold: Safari zooms the whole page
           in when a focused field renders below it, and it does not zoom back out — the
           reporter is left on a magnified, horizontally scrolling page mid-form. `font:
           inherit` alone would drop under it on any host with a smaller root size, so the
           floor is enforced here while still following a larger host size. */
        font-size: max(1rem, 16px);
        /* 44px is the same WCAG 2.5.5 target the buttons carry. A field is a pointer target
           too, and the padding above left these at 36px — under the floor the README states
           for every control, while the touch-target measurement only ever looked at buttons.
           min-height, not height: a textarea must still grow past it. */
        min-height: 44px;
        box-sizing: border-box;
    }
    .visual-feedback-dialog textarea { min-height: 6rem; resize: vertical; }

    /* The privacy acknowledgment is the one control a guest cannot submit without, and it was
       the UA default: a 13x13 box on a 20px-tall label block. Sized here rather than restyled —
       `appearance: none` would hand this package the tick, the checked, indeterminate and focus
       states, and the dark scheme that `color-scheme: light dark` above currently gets from the
       UA for free. 1.5rem clears the 24x24 WCAG 2.5.8 floor on its own. */
    .visual-feedback-dialog input[type="checkbox"] {
        flex: none;
        inline-size: 1.5rem;
        block-size: 1.5rem;
        margin: 0;
    }

    /* …and the 44x44 of WCAG 2.5.5 comes from the LABEL, because a label activates its control.
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
       a field label. */
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
    /* The attachment caps. Same muted treatment as the counter — both are secondary text
       next to a field, and --vf-muted is the one tone the contrast sweep already holds at
       AA in both schemes. A new color here would be a new thing to prove. */
    .visual-feedback-hint { display: block; margin-top: 0.25rem; font-size: 0.8125rem; color: var(--vf-muted); }

    /* The honeypot's concealment, as a RULE rather than only as an attribute.
       The markup carries both. A content security policy that allows this stylesheet through a
       nonce or hash while forbidding style attributes -- `style-src-attr 'none'`, ordinary
       hardening -- drops the attribute and keeps this, and that difference is not cosmetic: an
       exposed honeypot gets filled in by real reporters, and a honeypot hit shows the success
       screen on purpose, so the report is discarded while both sides believe it arrived.
       Off-screen rather than `display: none`, because a bot that skips undisplayed fields would
       skip the trap. */
    .visual-feedback-honeypot {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        left: -9999px;
    }

    .visual-feedback-dialog [role="alert"] { color: var(--vf-error); }

    /* The control the server marked invalid, for the reporter who can see it.
       Until this rule existed the error state was audible and invisible: `aria-invalid` told a
       screen reader which field was wrong while a sighted reporter had only the shared alert
       line and no idea which of eight controls it meant.
       Keyed on the ATTRIBUTE rather than a class, so it follows the server's verdict exactly and
       cannot drift from it -- and it therefore stays off for a rate limit or a disabled widget,
       which is the same discrimination the marking itself makes. The outline is drawn beside the
       border rather than replacing it, so a reporter who overrides --vf-border keeps both. */
    .visual-feedback-dialog [aria-invalid="true"] {
        border-color: var(--vf-error);
        outline: 1px solid var(--vf-error);
        outline-offset: -2px;
    }

    /* Announced but not shown: the character counter's settled value, so a screen reader hears
       the tally after a typing pause instead of on every keystroke, while the visible counter
       keeps updating live. `position: absolute` with no insets keeps the element at its static
       position, so it can never travel somewhere odd in a host page. */
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

    /* The capture preview. This class had no rule anywhere in the package, and an <img> with no
       max-width lays itself out at its INTRINSIC size: the shipped default (scale 2, viewport
       only) makes a 640x1136 PNG on a 320px phone and a 2560x1600 one at 1280x800 — rendered at
       640 CSS px inside a 232px column, and at 2560 inside a 510px one. The panel then scrolls in
       both axes and the reporter sees about a third of the picture whose entire purpose is that
       they see what they are about to send. A Tailwind host hides this behind preflight's
       `img { max-width: 100% }`; this is the tree that has no preflight, so the rule belongs
       here. Deliberately NO max-height: a cap was measured shrinking a portrait capture to
       128x227 in a 232px column, which works against the same purpose from the other side. */
    .visual-feedback-preview {
        display: block;
        max-width: 100%;
        height: auto;
        margin-top: 0.5rem;
        border: 1px solid var(--vf-border);
        border-radius: 6px;
        box-sizing: border-box;
    }

    .visual-feedback-preview-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    /* Attachments: a visible, focusable file input (native keyboard path) and remove
       buttons that are always visible and ≥44px (16px hover-only remove buttons are the common mistake). */
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

    /* ── The report browser ──────────────────────────────────────────────────────────
       Same custom properties as the widget, so a host that already overrode --vf-accent
       gets a browser that matches without touching anything else. No new tokens: a
       second palette to keep in sync is a second palette that drifts. */
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

    /* 44px on every control in the table, the same floor the widget holds. */
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

    /* The preview is a full-page capture and by far the heaviest thing here. The box keeps
       its space so the pane does not jump while it decodes. */
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
@endif
