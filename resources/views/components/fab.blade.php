{{--
    Single-action floating trigger — `<x-visual-feedback::fab />`. One click dispatches
    `visual-feedback:open` on the window, which the widget catches to open its modal. It
    is a plain button (aria-haspopup="dialog", ≥ 44px via the stylesheet), NOT a speed-dial
    menu, so a single tap opens feedback directly. Place it once per page.

    Props:
      position — bottom-end (default) | bottom-start | top-end | top-start. Read logically:
                 `end` is the side a line of text ends on, so a right-to-left page mirrors
                 it, exactly as the WireKit tree does. The physical spellings (bottom-right,
                 bottom-left, top-right, top-left) still work as their left-to-right reading.
      label    — button text; falls back to the widget heading. A slot overrides both.
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
<button
    type="button"
    x-data
    {{ $attributes->class(['visual-feedback-fab', 'visual-feedback-fab--'.\Pushery\VisualFeedback\Support\FabCorner::of($position)]) }}
    aria-haspopup="dialog"
    x-on:click="$dispatch('visual-feedback:open')"
>{{ $slot->isEmpty() ? ($label ?? __('visual-feedback::messages.widget.heading')) : $slot }}</button>
@endif
