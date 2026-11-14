{{--
    The report mail's theme: the mail theme the application configures, and the rules the themes
    Laravel ships do not carry.

    The subject and the message travel as fenced code, which keeps what a reporter typed inert and
    exactly as typed, and which the HTML part renders as <pre><code>. With no rule for it, a mail
    client keeps `white-space: pre`, so a long line runs off the right edge of a phone screen, and
    Laravel's themes leave <code> its monospace face, so the two texts a reader opens the mail for
    looked like a log. The rules below wrap them and set them in the face and size of the text
    around them. The lists of the mail are pairs of a label and a value, so they stand without
    bullets, and a label in the technical table keeps its line. The rules are inlined into those
    elements with the theme's own; they are echoed rather than written out, because this view
    renders CSS for the inliner, not markup. The theme is resolved the way Laravel resolves `mail.markdown.theme`: a
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

{!! 'pre { white-space: pre-wrap; overflow-wrap: anywhere; margin: 0 0 16px; font-size: 16px; line-height: 1.5em; } pre code { font-family: inherit; font-size: inherit; } .inner-body ul { list-style: none; margin: 0 0 16px; padding: 0; } .inner-body li { margin: 0 0 2px; } .inner-body .table td:first-child { white-space: nowrap; padding-right: 16px; }' !!}
