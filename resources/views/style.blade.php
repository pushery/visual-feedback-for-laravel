{{--
    The widget's stylesheet, for both view trees — framework-free, no Tailwind, no build step.
    Include it once in your layout <head>, whichever tree you serve:

        @include('visual-feedback::style')

    It writes a <link> to the sheet, which the package serves from its own route under an address
    that carries a digest of the sheet. A browser fetches it once and keeps it until the sheet
    changes, and a page carries one line instead of the rules. Where the sheet has to stand in the
    page itself, ask for that:

        @include('visual-feedback::style', ['inline' => true])

    or set `ui.stylesheet` to `inline` for every include at once.

    It is not a no-op under the WireKit tree. That tree gets the smaller sheet in
    `stylesheet-wirekit.blade.php`: the layout design tokens do not cover, such as the rhythm
    between field groups, the screenshot preview and its status, the required-field legend and the
    concealment rule for the honeypot. Leave the include out and the scripts component writes it
    itself, late in the body, and logs a warning that names the missing line.

    In the plain tree every color is a CSS custom property with a dark value, under
    `prefers-color-scheme: dark` by default or under the application's own selector where
    `ui.dark_mode` is `class`, so the widget takes its dark colors without a setting of its own,
    and a host can retint it by overriding the `--vf-*` properties. The rules themselves stand in
    `stylesheet.blade.php` and `stylesheet-wirekit.blade.php`; publish and edit them with:

        php artisan vendor:publish --tag=visual-feedback-views

    Under a nonce-based content security policy the <link>, or the inline block, carries the nonce
    passed as `@include('visual-feedback::style', ['nonce' => $nonce])`, or else the one the
    application gave `Vite::useCspNonce()`. A policy that allows `style-src 'self'` loads the link
    without one.
--}}
@php
    // Recorded before the branch below: the host wrote the include either way, and whether it
    // links the sheet or writes it into the page decides what this file renders, never whether
    // it was asked for.
    app(\Pushery\VisualFeedback\Support\StylesheetPresence::class)->markRendered();
    $nonce = app(\Pushery\VisualFeedback\Support\CspNonce::class)->resolve($nonce ?? null);
    $vfStylesheet = app(\Pushery\VisualFeedback\Support\Stylesheet::class);
@endphp
{{-- One complete element per branch. Livewire writes no morph markers inside a style element and
     pairs each closing tag with the last one opened before it, so a style element opened in one
     branch is closed in the same one. --}}
@if ($vfStylesheet->inline($inline ?? null))
<style @if ($nonce !== null) nonce="{{ $nonce }}" @endif>
{!! $vfStylesheet->css() !!}
</style>
@else
<link rel="stylesheet" href="{{ $vfStylesheet->url() }}" @if ($nonce !== null) nonce="{{ $nonce }}" @endif>
@endif
