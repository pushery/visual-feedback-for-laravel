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
     * @return array{strategy: string, scale: int|string, viewportOnly: bool, darkFallback: string, redactAttribute: string, iframePlaceholder: bool, flattenCustomElements: bool, debug: bool}
     */
    public static function screenshot(): array
    {
        return [
            'strategy' => is_string($strategy = config('visual-feedback.screenshot.strategy')) ? $strategy : 'auto',
            // A number, or the literal 'device' (→ devicePixelRatio client-side); anything else
            // is a mistyped config and falls back to the safe default.
            'scale' => is_numeric($scale = config('visual-feedback.screenshot.scale')) ? (int) $scale : ($scale === 'device' ? 'device' : 2),
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
