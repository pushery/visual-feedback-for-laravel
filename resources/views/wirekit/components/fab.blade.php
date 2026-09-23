{{--
    The WireKit single-action FAB — now WireKit's own <x-wirekit::fab.button>.

    This file used to be a workaround. WireKit's <x-wirekit::fab> is a speed-dial
    (aria-haspopup="menu", arrow navigation), the wrong semantics for a one-click "open
    feedback" trigger, so the widget carried a token-styled button of its own. v2.20.0 shipped
    the single-action variant but could only place it along the bottom edge and
    announced a text button as "Action" — two deltas that made adoption a LOSS of
    capability. v2.21.0 closed both, so the workaround is gone: no positioning arithmetic,
    no safe-area composition, no hand-built accessible name.

    `visual-feedback.ui.position` is LOGICAL in both trees, which is the vocabulary WireKit's
    `position` already spoke: `end` follows the writing direction, so a right-to-left
    application mirrors the trigger. The plain tree used to read the same value physically, and
    the two trees then put it in different corners of a right-to-left page. FabCorner is the one
    place both trees resolve the value, so they cannot drift apart again.

    Props: position — bottom-end (default) | bottom-start | top-end | top-start, or the
    physical spellings (bottom-right, bottom-left, top-right, top-left) as their left-to-right
    reading.
--}}
@props([
    'position' => 'bottom-end',
    'label' => null,
])
{{-- Master switch. A host places this trigger in ITS own layout, so the widget cannot take it
     away by rendering nothing itself — the trigger has to ask too, or an operator who switched
     the package off is left with a button that opens an empty dialog. `visual-feedback.enabled`
     is documented in the shipped config as "the widget renders nothing"; this is part of what
     makes that sentence true. --}}
@if (app(\Pushery\VisualFeedback\Support\WidgetAvailability::class)->forThisRequest())
@php
    // The corner onto WireKit's two logical axes. All four corners are reachable — the gap that
    // made the earlier release unadoptable for this widget.
    [$placement, $inline] = match (\Pushery\VisualFeedback\Support\FabCorner::of($position)) {
        'bottom-end' => ['block-end', 'end'],
        'bottom-start' => ['block-end', 'start'],
        'top-end' => ['block-start', 'end'],
        'top-start' => ['block-start', 'start'],
    };

    // The glyph. WireKit draws a plus when it is handed none, and a plus reads as "create something":
    // beside a list with its own New button the trigger looked like one more of them. So it names an
    // icon that means feedback, `ui.fab_icon`, which is `message` unless a host says otherwise.
    //
    // Only an alias WireKit declares is handed over. `message` arrived in WireKit v2.25.0 and
    // `isIconAlias()` in v2.27.0, and a name WireKit does not declare falls through to the icon set's
    // own naming, where it may name no glyph at all. On an older WireKit, and for a name it does not
    // know, the plus stays, which is what this trigger showed before. The `method_exists` half cannot be
    // exercised by this package's suites, whose WireKit has the method. Without an icon set installed,
    // WireKit draws its plus whatever it is handed.
    $icon = config('visual-feedback.ui.fab_icon', 'message');
    $icon = is_string($icon) && method_exists(\Pushery\WireKit\WireKit::class, 'isIconAlias') && \Pushery\WireKit\WireKit::isIconAlias($icon)
        ? $icon
        : null;
@endphp
{{-- No wrapper. `.visual-feedback-fab` goes straight onto the button — it is the marker the
     capture pipeline hides itself by, and it has to sit on the element that IS the FAB.
     Wrapping instead was measured to break: WireKit positions the button itself now, so the
     wrapper collapses to zero height and a click on the marker hits nothing.

     The name travels as `label`, not as slot text, and that is not a preference. WireKit's FAB
     is a circular 56px ICON button, and it renders whatever the slot holds inside
     `<span aria-hidden="true">` — the span is for decorative icon markup. Put words there and
     they are both clipped by the circle AND invisible to assistive tech, and v2.21.0 emits no
     `aria-label` in that case, so the button ends up with no accessible name at all. Filed
     upstream; `label` is the path WireKit's own logic sanctions.

     So the WireKit tree's trigger is an icon, where the plain tree's is a text button. That is
     the point of this tree — it looks like the design system, not like us — and the accessible
     name is the widget heading either way. Named in the integration contract. --}}
<x-wirekit::fab.button
    class="visual-feedback-fab"
    :placement="$placement"
    :position="$inline"
    :icon="$icon"
    :label="$label ?? __('visual-feedback::messages.widget.heading')"
    haspopup="dialog"
    x-data
    x-on:click="$dispatch('visual-feedback:open')"
>{{ $slot }}</x-wirekit::fab.button>
@endif
