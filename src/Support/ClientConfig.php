<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * The single source for the client-side capture config — the exact, type-guarded subset of
 * `screenshot.*` the browser needs (render scale, viewport crop, dark-mode fallback, the
 * capture strategy and the redaction attribute). Both the widget's per-instance Alpine config
 * and the global `<x-visual-feedback::scripts>` config island read from here, so the two can
 * never drift. Every value is guarded to a safe default, since a mistyped config must not
 * reach the client as an array or null.
 */
final class ClientConfig
{
    /**
     * @return array{strategy: string, scale: float|int|string, viewportOnly: bool, darkFallback: string, redactAttribute: string, iframePlaceholder: bool, flattenCustomElements: bool, debug: bool}
     */
    public static function screenshot(): array
    {
        return [
            'strategy' => is_string($strategy = config('visual-feedback.screenshot.strategy')) ? $strategy : 'auto',
            // A number, or the literal 'device' (→ devicePixelRatio client-side); anything else
            // is a mistyped config and falls back to the safe default.
            //
            // ⚠️ INTEGRAL VALUES STAY INTEGERS, FRACTIONAL ONES SURVIVE. This was `(int) $scale`,
            // which truncated: a host following the capture page's advice to "lower
            // `screenshot.scale`" set `0.5`, `env()` handed back the string, and `(int) "0.5"` is
            // `0`. The client's `clampScale` then reads `Math.max(0.1, 0 || 1)` — `0` is falsy —
            // so the capture rendered at scale 1, LARGER than configured, and the
            // capture-and-reject loop the documentation promises to escape stayed shut. `1.5`
            // became `1` the same way, silently.
            //
            // Integral values keep their int type because the JSON island is a public surface and
            // `3` reading as `3.0` there would be a gratuitous change; the guard on PHP_INT_MAX
            // keeps an absurd configured value from wrapping into a negative int.
            'scale' => is_numeric($scale = config('visual-feedback.screenshot.scale'))
                ? (($float = (float) $scale) === floor($float) && abs($float) < PHP_INT_MAX ? (int) $float : $float)
                : ($scale === 'device' ? 'device' : 2),
            'viewportOnly' => (bool) config('visual-feedback.screenshot.viewport_only', true),
            'darkFallback' => is_string($color = config('visual-feedback.screenshot.dark_fallback_color')) ? $color : '#111827',
            'redactAttribute' => is_string($attr = config('visual-feedback.screenshot.redact_attribute')) ? $attr : 'data-visual-feedback-redact',
            'iframePlaceholder' => (bool) config('visual-feedback.screenshot.iframe_placeholder', true),
            'flattenCustomElements' => (bool) config('visual-feedback.screenshot.flatten_custom_elements', true),
            // The capture module has accepted a `debug` option since it was written, and this
            // array is passed to it verbatim — but the key was never put in, so
            // VISUAL_FEEDBACK_SCREENSHOT_DEBUG=true changed nothing while the shipped config
            // said it "turns on the library's own logging" and named the failures that are
            // invisible without it. One line, and the promise is kept.
            'debug' => (bool) config('visual-feedback.screenshot.debug', false),
        ];
    }
}
