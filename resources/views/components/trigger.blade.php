{{--
    Inline trigger — `<x-visual-feedback::trigger>Give feedback</x-visual-feedback::trigger>`.
    An in-flow button (no fixed positioning) you place anywhere — a nav item, a footer link,
    a settings row. Like the FAB it dispatches `visual-feedback:open` on the window; the
    widget opens its modal in response. The slot is the label; `label` is the fallback.
--}}
@props([
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
    {{ $attributes->merge(['class' => 'visual-feedback-trigger']) }}
    aria-haspopup="dialog"
    x-on:click="$dispatch('visual-feedback:open')"
>{{ $slot->isEmpty() ? ($label ?? __('visual-feedback::messages.widget.heading')) : $slot }}</button>
@endif
