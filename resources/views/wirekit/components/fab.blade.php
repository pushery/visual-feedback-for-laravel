{{--
    The WireKit single-action FAB — now WireKit's own <x-wirekit::fab.button>.

    This file used to be a workaround. WireKit's <x-wirekit::fab> is a speed-dial
    (aria-haspopup="menu", arrow navigation), the wrong semantics for a one-click "open
    feedback" trigger, so the widget carried a token-styled button of its own. v2.20.0 shipped
    the single-action variant but could only place it along the bottom edge and
    announced a text button as "Action" — two deltas that made adoption a LOSS of
    capability. v2.21.0 closed both, so the workaround is gone: no positioning arithmetic,
    no safe-area composition, no hand-built accessible name.

    ONE difference is worth stating rather than leaving to be discovered.
    `visual-feedback.ui.position` is named PHYSICALLY (bottom-right), and the plain view tree
    implements it that way with `right` and `left`. WireKit's `position` is LOGICAL — `end`
    follows the writing direction. In a left-to-right application the two trees agree exactly;
    in a right-to-left one this tree mirrors and the plain tree does not. WireKit's behavior is
    the better default, so this maps onto it rather than fighting it. Unifying the vocabulary
    across both trees is its own change, tracked separately, because it would alter a documented
    config value.

    Props: position — bottom-right (default) | bottom-left | top-right | top-left.
--}}
@props([
    'position' => 'bottom-right',
    'label' => null,
])
{{-- Master switch. A host places this trigger in ITS own layout, so the widget cannot take it
     away by rendering nothing itself — the trigger has to ask too, or an operator who switched
     the package off is left with a button that opens an empty dialog. `visual-feedback.enabled`
     is documented in the shipped config as "the widget renders nothing"; this is part of what
     makes that sentence true. --}}
@if (app(\Pushery\VisualFeedback\Support\Settings::class)->enabled())
@php
    // The physical config vocabulary onto WireKit's two logical axes. All four corners are
    // reachable — the gap that made the earlier release unadoptable for this widget.
    [$placement, $inline] = match ($position) {
        'bottom-left' => ['block-end', 'start'],
        'top-right' => ['block-start', 'end'],
        'top-left' => ['block-start', 'start'],
        default => ['block-end', 'end'],
    };
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
    :label="$label ?? __('visual-feedback::messages.widget.heading')"
    haspopup="dialog"
    x-data
    x-on:click="$dispatch('visual-feedback:open')"
>{{ $slot }}</x-wirekit::fab.button>
@endif
