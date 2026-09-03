{{--
    What the widget renders while `visual-feedback.enabled` is false.

    A Livewire component must return a single root element, so "renders nothing" is exactly
    this: one empty, hidden element carrying no trigger, no form, no floating button and no
    text. Nothing a reporter can see, focus, or tab into.

    `hidden` rather than an empty <div> alone, because a host stylesheet that gives every
    direct child of its layout a margin or a min-height would otherwise leave a gap on the
    page where the widget used to be — a switch that is off should cost no layout.

    The endpoint half of the switch lives in SubmitReport::handle(), and it is the half that
    matters: this component is registered by name and is reachable without the page that draws
    it, so a stale tab still gets a server-side refusal rather than this file.
--}}
<div hidden></div>
