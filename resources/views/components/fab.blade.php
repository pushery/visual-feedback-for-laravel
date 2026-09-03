{{--
    Single-action floating trigger — `<x-visual-feedback::fab />`. One click dispatches
    `visual-feedback:open` on the window, which the widget catches to open its modal. It
    is a plain button (aria-haspopup="dialog", ≥ 44px via the stylesheet), NOT a speed-dial
    menu, so a single tap opens feedback directly. Place it once per page.

    Props:
      position — bottom-right (default) | bottom-left | top-right | top-left
      label    — button text; falls back to the widget heading. A slot overrides both.
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
<button
    type="button"
    x-data
    {{ $attributes->class(['visual-feedback-fab', 'visual-feedback-fab--'.$position]) }}
    aria-haspopup="dialog"
    x-on:click="$dispatch('visual-feedback:open')"
>{{ $slot->isEmpty() ? ($label ?? __('visual-feedback::messages.widget.heading')) : $slot }}</button>
@endif
