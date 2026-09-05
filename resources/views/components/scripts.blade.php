{{--
    <x-visual-feedback::scripts /> — loads the capture bundle and hands the client its config.

    Place it once IN YOUR LAYOUT, before </body> — the same layout the widget goes in. Per-page
    placement reads like a saving (the ~200 KB bundle only on feedback-bearing pages) and buys a
    problem under `wire:navigate`: a page first reached BY a navigation runs its body scripts
    after Alpine has already walked the new body, so the capture island is initialized before the
    component it names exists. The bundle repairs that itself, but a repair is a fallback, not a
    plan — a layout tag pays the cost once, on the first page, and needs none of it.

    ALL visible text comes from the widget's Blade/lang, never this bundle — the config island
    carries only BEHAVIOR (locale, capture clamps, redact attribute), no user-facing strings, so
    the committed dist/ can never fall behind the 7 locales. The base URL is the `ui.assets`
    config or the published `vendor/visual-feedback` path.
--}}
@php
    $assetBase = rtrim((string) (config('visual-feedback.ui.assets') ?: asset('vendor/visual-feedback')), '/');

    // Subresource Integrity, and ONLY where it can mean something: the bundles have to be coming
    // from an origin this application does not own. Served from the host's own `public/` they are
    // same-origin already and the digest would be checking a file against itself.
    //
    // Opt-in because it can refuse the page. A CDN that re-minifies, or that still carries the
    // previous version, fails the check and the browser drops the script — leaving a widget that
    // renders perfectly and does nothing, which is the failure this package spends most of its
    // guards on.
    $external = is_string(config('visual-feedback.ui.assets')) && config('visual-feedback.ui.assets') !== '';
    $wantsIntegrity = $external && (bool) config('visual-feedback.ui.assets_integrity');
    $integrity = fn (string $bundle): ?string => $wantsIntegrity
        ? app(\Pushery\VisualFeedback\Support\PublishedBundle::class)->integrity($bundle)
        : null;
    $clientConfig = [
        'locale' => app()->getLocale(),
        'screenshot' => \Pushery\VisualFeedback\Support\ClientConfig::screenshot(),
    ];

    // A cache-busting token, the way livewire/livewire versions its own published asset: the URL
    // moves when the package moves, so a `vendor:publish --force` after an upgrade is not served
    // out of a browser or CDN cache. A branch install ("dev-main") keeps its version string
    // across every update, so there the resolved commit is what actually moves. CSP is
    // untouched — a `script-src` source expression matches on the path, never on the query.
    $package = 'pushery/visual-feedback-for-laravel';
    $bundleVersion = 'dev';

    if (class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled($package)) {
        $bundleVersion = (string) (\Composer\InstalledVersions::getPrettyVersion($package) ?: 'dev');
        $reference = \Composer\InstalledVersions::getReference($package);

        if (is_string($reference) && str_starts_with($bundleVersion, 'dev-')) {
            $bundleVersion .= '.'.substr($reference, 0, 8);
        }
    }

    // The token above solves "republished but still cached". This line answers the other failure
    // shape: NOT republished at all, so `public/` still holds the previous release's bundle. It
    // measures server-side out of `vendor/`, which is why it works on the FIRST request after an
    // upgrade — a client-side stamp would be executed by the stale copy itself. It reads nothing
    // from disk unless APP_DEBUG is on, and renders no inline script, because this package
    // carries no CSP nonce to attach to one.
    app(\Pushery\VisualFeedback\Support\PublishedBundle::class)->warnIfUnusable();
@endphp
{{-- Master switch. The host places this tag in ITS own layout, so the widget cannot take it away
     by rendering nothing itself — this component has to ask too, exactly as the fab and the
     trigger do, or an operator who switched the package off still ships the whole renderer. --}}
@if (app(\Pushery\VisualFeedback\Support\Settings::class)->enabled())
<script type="application/json" data-visual-feedback-config>{!! json_encode($clientConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR) !!}</script>
{{-- `strategy = off` is documented as "disables the screenshot entirely", and the widget keeps
     that promise: at `off` it renders no `x-data="visualFeedbackCapture(…)"` island at all, so
     nothing on the page needs the renderer. At `native` it DOES render one — the bundle is the
     only thing that registers that component — so the tag stays there even though html2canvas
     can never run. Saving the renderer at `native` means shipping the ESM entry instead, which
     is a different delivery contract, not a condition on this line.

     `data-navigate-once` keeps Livewire's navigate plugin from re-running the tag on every SPA
     navigation. Without it the plugin clones and re-executes every body script it swaps in
     (`defer` does not apply to a cloned element), so the bundle would be re-parsed per
     navigation and leave one more `alpine:init` listener — and its whole module scope —
     retained on `window` each time. --}}
{{-- The widget bundle, ALWAYS — it is what registers the Alpine components the templates bind
     to, and under Alpine's CSP build a component that is not registered means the element is
     never initialized at all: an empty scope, and every directive on it silently dead.
     Unconditional for that reason and affordable because of its size: about 4 KB against the
     capture bundle's 268. --}}
<script src="{{ $assetBase }}/visual-feedback-widget.iife.js?id={{ rawurlencode($bundleVersion) }}" @if ($integrity('visual-feedback-widget.iife.js')) integrity="{{ $integrity('visual-feedback-widget.iife.js') }}" crossorigin="anonymous" @endif data-navigate-once defer></script>
@if ($clientConfig['screenshot']['strategy'] !== 'off')
<script src="{{ $assetBase }}/visual-feedback.iife.js?id={{ rawurlencode($bundleVersion) }}" @if ($integrity('visual-feedback.iife.js')) integrity="{{ $integrity('visual-feedback.iife.js') }}" crossorigin="anonymous" @endif data-navigate-once defer></script>
@endif
@endif
