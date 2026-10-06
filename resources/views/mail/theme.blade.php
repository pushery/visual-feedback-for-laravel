{{--
    The report mail's theme: the mail theme the application configures, and one rule the themes
    Laravel ships do not carry.

    The subject and the message travel as fenced code, which the HTML part renders as <pre>. With
    no rule for it, a mail client keeps `white-space: pre`, and a long line runs off the right edge
    of a phone screen instead of wrapping. The rule below is inlined into those elements with the
    theme's own rules; it is echoed rather than written out, because this view renders CSS for the
    inliner, not markup. The theme is resolved the way Laravel resolves `mail.markdown.theme`: a
    view `mail.<name>` in the application first, then a namespaced view, then a theme file.
--}}
@php
    $configured = config('mail.markdown.theme', 'default');
    $configured = is_string($configured) && $configured !== '' ? $configured : 'default';
    $vfMailTheme = view()->exists($vfCustomTheme = \Illuminate\Support\Str::start($configured, 'mail.'))
        ? $vfCustomTheme
        : (str_contains($configured, '::') ? $configured : 'mail::themes.'.$configured);

    if ($vfMailTheme === 'visual-feedback::mail.theme') {
        $vfMailTheme = 'mail::themes.default';
    }
@endphp
@include($vfMailTheme)

{!! 'pre { white-space: pre-wrap; overflow-wrap: anywhere; }' !!}
