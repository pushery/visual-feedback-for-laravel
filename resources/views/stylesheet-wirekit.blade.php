{{--
    The WireKit tree's stylesheet as text. Pushery\VisualFeedback\Support\Stylesheet serves it in
    place of `stylesheet.blade.php` while the widget resolves to the WireKit template, so the
    application's design tokens are not fought by a second palette. Like that view, it holds no
    Blade condition, so no morph marker of Livewire's can land in its text when a component's view
    carries the include.
--}}
{{-- The WireKit tree gets layout only, and that is the whole difference from the plain tree's sheet.

     What the guard is for is color and design: a second set of --vf-* colors, borders and radii
     would fight the application's own tokens, and that is why the plain tree's sheet stays away
     from that tree. It does not follow that the tree needs no CSS at all. The WireKit tree emits
     the same class names, so without this block `.visual-feedback-preview-actions` loses
     `display: flex` and its gap, and the Attach / Discard / Retake row renders with no space
     between the buttons at all.

     Every value below is a WireKit token with the plain tree's own number as its fallback, so a
     host who tunes their spacing scale takes this along and one who never loaded WireKit's CSS
     still gets the spacing rather than none.

     The honeypot rule is here too. Both trees hide that field two ways -- an inline `style`
     attribute and this class -- because a policy that forbids style attributes
     (`style-src-attr 'none'`, which any `style-src` without an attribute clause implies) drops
     the first one, and then the class is all that hides it, in either tree. A visible honeypot
     is filled in by real people, and a honeypot hit is answered with the success screen on
     purpose -- so their report would be thanked for and discarded. That is the one failure here
     worth more than a margin. --}}
    {{-- Concealment, not decoration. See the note above. --}}
    .visual-feedback-honeypot {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        left: -9999px;
    }
    {{-- The kit's spinner, centered in the modal's body until the form arrives with the round trip
         that opens it. --}}
    .visual-feedback-loading {
        display: flex;
        justify-content: center;
        padding-block: var(--space-wk-md, 1rem);
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

    {{-- The report browser's message keeps the reporter's line breaks, as in the plain tree. The
         kit's text component collapses white space, and a long unbroken token wraps as there. --}}
    .visual-feedback-browser-message {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    {{-- The hidden heading of the actions column is positioned absolutely, and its cell is its
         containing block. WireKit releases before 2.51 do not make the table's scroller one, so on
         a narrow screen the heading sat at the far end of the table, outside the scroller, and
         widened the page. --}}
    .visual-feedback-browser-actions-head {
        position: relative;
    }

    {{-- The row under the preview. Without `display: flex` the three buttons are inline boxes
       with a word space between them, which is why this one reads as broken rather than tight.

       The step above it is the form's step, not a smaller one of its own: a row spaced tighter
       than every direct child of the form reads as cramped, because it sits between two things
       spaced wider than it is. --}}
    .visual-feedback-preview-actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--gap-wk-sm, 0.5rem);
        margin-block-start: var(--space-wk-md, 1rem);
    }

    {{-- The block while a capture waits for a decision, attached or discarded, neither yet. The
       sentence naming that state stands at the end of the form; this border shows which part of
       the screen it means. WireKit's warning text tone rather than its fill, because a 1px line
       needs the 3:1 of WCAG 1.4.11 against the surface and the fill is too light for it. --}}
    .visual-feedback-captured-pending {
        padding: var(--space-wk-md, 1rem);
        border: 1px solid var(--color-wk-warning-text, #b45309);
        border-radius: var(--radius-wk-md, 0.5rem);
    }

    {{-- The legend's star in the tone WireKit's label gives the star on every required control,
       so the legend explains the mark the reporter actually sees, and the sentence muted like
       the other notes of this form. --}}
    .visual-feedback-required-mark {
        color: var(--color-wk-danger-text, #b91c1c);
    }

    .visual-feedback-required-legend {
        font-size: var(--text-wk-sm, 0.8125rem);
        color: var(--color-wk-text-muted, #6b7280);
    }

    .visual-feedback-required-legend .visual-feedback-required-mark {
        margin-inline-end: var(--space-wk-xs, 0.25rem);
    }

    {{-- The host's notice above the fields, in the text tone of the kit, spaced off the first field. --}}
    .visual-feedback-notice {
        margin-block-end: var(--space-wk-sm, 0.5rem);
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
       SE profile it shrank a portrait capture to 128x227 inside a 232px column -- 55% of the space
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

    {{-- The box is the kit's, and what this rule adds is the gap above it and a fallback.

       A success box of the package's own -- border, left rule, radius, tone, weight -- matched
       to the alert by hand from the same token scale would drift from it with the next token
       release, and the success box and the danger box beside it would stop being the same shape.
       That drift is what the alert component exists to prevent.

       The appearance comes from <x-wirekit::alert intent="success">, and these declarations are
       the fallback beneath it, in `:where()` so they carry zero specificity and lose to the kit
       wherever its utilities are compiled. A host without a Tailwind build -- the case this
       stylesheet exists for -- would otherwise get a confirmation with no box at all. There is no
       glyph rule: the kit draws its own icon, and the package paints no second one beside it. --}}
    :where(.visual-feedback-success) {
        margin-block-start: var(--space-wk-sm, 0.5rem);
        padding: var(--space-wk-sm, 0.5rem) var(--space-wk-md, 0.75rem);
        border: 1px solid var(--color-wk-border-success, #15803d);
        border-inline-start: 4px solid var(--color-wk-border-success, #15803d);
        border-radius: var(--radius-wk-md, 0.5rem);
        color: var(--color-wk-success-text, #15803d);
        font-weight: var(--font-wk-heading-weight, 600);
    }

    {{-- The buttons that are bare siblings of a paragraph, a link or a status box, and therefore
       have no selector of their own -- not even one a host could hang their own rule on.

       The retake button that stands alone under the capture status is one of them and the
       easiest to miss: without a rule it sits flush against the green "screenshot attached" box
       in both trees. The done button beside "report another" is another: where the two labels
       do not fit on one line, as in German on a phone, it wraps under its neighbor and needs the
       same space above it. --}}
    .visual-feedback-capture,
    .visual-feedback-submit,
    .visual-feedback-retake,
    .visual-feedback-report-another,
    .visual-feedback-done {
        margin-block-start: var(--space-wk-sm, 0.5rem);
    }

    {{-- ── Vertical rhythm ─────────────────────────────────────────────────────────────
       The gap between one field group and the next. The plain tree has carried this since it
       existed, as `label { margin-top: 0.75rem }`; this tree renders WireKit components instead
       of labels of its own, and so had no rule for it -- every group sat flush against the one
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

    {{-- ...and the two children that must not count in that rhythm, because they take no room.

       Measured in the live dialog: the honeypot is 1px tall and the challenge slot is 0px while
       no provider renders into it, and each still collected a full step. With no guest fields on
       screen that put two steps plus the panel padding between the header and the first field --
       the gap the report is about.

       `:empty` is not enough for the honeypot: it has children, it is just positioned out of the
       flow, so it is named directly. The challenge slot is an ordinary block that is empty until a
       host injects something, but `:empty` is only half the question there: an invisible provider
       fills it with elements that take no room, and the slot is then not empty and still zero
       high. The widget measures it and sets `data-visual-feedback-collapsed` while it has no
       height (watchChallengeHeight in the widget's script), so its spacing returns the moment it shows.

       The margin is removed from the element after them as well: the owl selector spaces a child
       from its predecessor, so skipping a zero-height predecessor means the next visible element
       must not inherit a step from it either. That is what the second selector does. --}}
    .visual-feedback-panel form > .visual-feedback-honeypot,
    .visual-feedback-panel form > .visual-feedback-challenge:is(:empty, [data-visual-feedback-collapsed]) {
        margin-block-start: 0;
    }

    .visual-feedback-panel form > .visual-feedback-honeypot + *,
    .visual-feedback-panel form > .visual-feedback-challenge:is(:empty, [data-visual-feedback-collapsed]) + * {
        margin-block-start: 0;
    }

    {{-- The group around the upload field and its list of files, where a removal hands focus back.
       The list sat one step below the field as a child of the form, and this keeps that step inside
       the group. --}}
    .visual-feedback-attachments > * + * {
        margin-block-start: var(--space-wk-md, 1rem);
    }

    {{-- Every rejection the reporter can see, in a box that reads as one.
       Owner directive: an error message is always in the red box.

       This tree had no error styling at all -- not even the tint the plain tree gives every
       `[role="alert"]` -- so "something went wrong, please try again" rendered in body color at
       body weight, indistinguishable from the attachment caps a few lines above it.

       Painted from a class the server sets, never from `:not(:empty)`: all four alert regions
       are live regions that must be in the markup before they have anything to say, and they
       therefore always contain Blade's leftover whitespace, which `:empty` does not match.

       WireKit's own danger tokens, not a palette of this package's -- `--color-wk-danger-text` is the
       tone that design system already proves against its surfaces, and inventing a second red
       here is how a widget stops looking like the app it is embedded in. The fallbacks are the
       plain tree's own error tone, so a host that loads this tree without WireKit's stylesheet
       still gets a red box rather than an unpainted one. --}}
    {{-- The box is the kit's, and this is the fallback underneath it -- which is why it is wrapped
       in `:where()`.

       The first attempt deleted these declarations outright, on the reasoning that a hand copy of
       the alert component's shape drifts from it. True, and it broke something the browser suite
       caught: WireKit's own box is Tailwind utilities that the consuming app compiles. A host
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

    {{-- The question mark WireKit puts beside a field's label is a 24px button, the target size of
       WCAG 2.2. On a touch screen every other control of this form clears 44px, and this one reaches it
       the way WireKit's own file-upload remove button does: through a transparent `::before` centered
       on it, so the mark keeps its size and the label row keeps its height. --}}
    @media (pointer: coarse) {
        .visual-feedback-panel [data-wk-field-help] button {
            position: relative;
        }

        .visual-feedback-panel [data-wk-field-help] button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: var(--size-wk-touch-target, 2.75rem);
            height: var(--size-wk-touch-target, 2.75rem);
            margin-top: calc(var(--size-wk-touch-target, 2.75rem) / -2);
            margin-left: calc(var(--size-wk-touch-target, 2.75rem) / -2);
        }
    }
    {{-- The screenshot editor, in WireKit tokens with the plain tree's values as fallbacks. Its
       controls are built by the editor's bundle, not by the kit, so they take their look from the
       kit's tokens here. A button and a number field are bounded by `--color-wk-border-strong`, the
       token the kit's own fields use: `--color-wk-border` is a separator, about 1.3:1 against the
       light surface, and a control's edge needs 3:1.

       No color-scheme of its own: the kit declares it on the root and on its `.dark` and `.light`
       scopes, from WireKit 2.29.0 on, below this tree's floor, and the editor inherits the page's.
       `light dark` here followed the operating system while the kit's tokens followed the class,
       so the editor's own controls came out light on a dark page, and the reverse. --}}
    .visual-feedback-editor {
        box-sizing: border-box;
        margin: auto;
        width: min(60rem, calc(100vw - 2rem));
        max-height: calc(100vh - 2rem);
        max-height: calc(100dvh - 2rem);
        padding: var(--space-wk-md, 1rem);
        border: 1px solid var(--color-wk-border, #7b8390);
        border-radius: var(--radius-wk-lg, 10px);
        background: var(--color-wk-bg-elevated, var(--color-wk-bg, #ffffff));
        color: var(--color-wk-text, #111827);
        box-shadow: var(--shadow-wk-lg, none);
        overflow: auto;
    }
    .visual-feedback-editor::backdrop { background: var(--color-wk-overlay, rgba(17, 24, 39, .5)); }
    .visual-feedback-editor :focus-visible {
        outline: 3px solid var(--color-wk-ring, #2563eb);
        outline-offset: 2px;
    }
    .visual-feedback-editor-title {
        margin: 0 0 var(--space-wk-sm, 0.75rem);
        font-size: 1.125rem;
        font-weight: var(--font-wk-heading-weight, 600);
    }
    .visual-feedback-editor-body {
        display: grid;
        gap: var(--space-wk-sm, 0.75rem);
    }
    .visual-feedback-editor-stage {
        position: relative;
        justify-self: center;
        max-width: 100%;
        user-select: none;
        -webkit-user-select: none;
    }
    @media (pointer: coarse) {
        .visual-feedback-editor-stage { max-width: calc(100% - 44px); }
    }
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
    .visual-feedback-editor-shade { fill: var(--color-wk-overlay, rgba(17, 24, 39, .55)); }
    {{-- The crop's outline: a dark line over a light one, so it shows on a light picture as on a dark one, and
       while the crop still takes the whole picture, where there is no shade to tell its edge by. --}}
    .visual-feedback-editor-outline {
        fill: none;
        stroke: #ffffff;
        stroke-width: 4;
    }
    .visual-feedback-editor-outline-dark {
        stroke: var(--color-wk-text, #111827);
        stroke-width: 2;
    }
    .visual-feedback-editor-region {
        position: absolute;
        box-sizing: border-box;
        cursor: move;
        touch-action: none;
    }
    .visual-feedback-editor-handle {
        position: absolute;
        box-sizing: border-box;
        width: 24px;
        height: 24px;
        margin: -12px 0 0 -12px;
        border: 2px solid var(--color-wk-accent, #2563eb);
        border-radius: var(--radius-wk-full, 50%);
        background: #ffffff;
        touch-action: none;
    }
    @media (pointer: coarse) {
        .visual-feedback-editor-handle {
            width: var(--size-wk-touch-target, 44px);
            height: var(--size-wk-touch-target, 44px);
            margin: calc(var(--size-wk-touch-target, 44px) / -2) 0 0 calc(var(--size-wk-touch-target, 44px) / -2);
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
        gap: var(--space-wk-sm, 0.5rem) var(--space-wk-md, 0.75rem);
    }
    .visual-feedback-editor-toolbar {
        display: grid;
        gap: var(--space-wk-sm, 0.5rem);
    }
    .visual-feedback-editor-row {
        --visual-feedback-editor-group-gap: var(--space-wk-md, 1rem);
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-wk-sm, 0.5rem) var(--visual-feedback-editor-group-gap);
    }
    .visual-feedback-editor-actions { justify-content: flex-end; }
    .visual-feedback-editor-field {
        display: grid;
        gap: var(--space-wk-xs, 0.25rem);
        font-size: var(--text-wk-sm, 0.875rem);
    }
    .visual-feedback-editor-field-input {
        box-sizing: border-box;
        width: 7rem;
        min-height: var(--size-wk-touch-target, 44px);
        padding: var(--space-wk-xs, 0.5rem);
        border: 1px solid var(--color-wk-border-strong, #7b8390);
        border-radius: var(--radius-wk-md, 6px);
        background: var(--color-wk-bg-input, var(--color-wk-bg, #ffffff));
        color: var(--color-wk-text, #111827);
        font: inherit;
        font-size: 16px;
    }
    .visual-feedback-editor-button {
        min-width: var(--size-wk-touch-target, 44px);
        min-height: var(--size-wk-touch-target, 44px);
        padding: var(--space-wk-xs, 0.5rem) var(--space-wk-md, 1rem);
        border: 1px solid var(--color-wk-border-strong, #7b8390);
        border-radius: var(--radius-wk-md, 6px);
        background: var(--color-wk-bg-muted, #f3f4f6);
        color: var(--color-wk-text, #111827);
        font: inherit;
        cursor: pointer;
    }
    .visual-feedback-editor-button:disabled { cursor: not-allowed; opacity: .55; }
    .visual-feedback-editor-apply {
        border-color: var(--color-wk-accent, #2563eb);
        background: var(--color-wk-accent, #2563eb);
        color: var(--color-wk-accent-fg, #ffffff);
        font-weight: 600;
    }
    .visual-feedback-editor-group {
        position: relative;
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-wk-sm, 0.5rem) var(--space-wk-xs, 0.25rem);
    }
    .visual-feedback-editor-group:not(:first-child, [data-line-start])::before {
        content: '';
        position: absolute;
        inset-block: 0;
        inset-inline-start: calc(var(--visual-feedback-editor-group-gap) / -2 - 0.5px);
        border-inline-start: 1px solid var(--color-wk-border, #7b8390);
    }
    .visual-feedback-editor-button[aria-pressed="true"] {
        border-color: var(--color-wk-accent, #2563eb);
        box-shadow: inset 0 0 0 2px var(--color-wk-accent, #2563eb);
        font-weight: 600;
    }
    .visual-feedback-editor-color {
        width: var(--size-wk-touch-target, 44px);
        padding: 0;
        background: var(--visual-feedback-editor-swatch, transparent);
    }
    .visual-feedback-editor-color[aria-pressed="true"] {
        box-shadow: inset 0 0 0 3px var(--color-wk-bg, #ffffff), 0 0 0 2px var(--color-wk-accent, #2563eb);
    }
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
        stroke: var(--color-wk-text, #111827);
        stroke-width: 2;
        stroke-dasharray: 6 4;
    }
