<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback;

use Closure;
use Pushery\VisualFeedback\Abuse\AbuseGateRegistry;
use Pushery\VisualFeedback\Channels\ChannelRegistry;
use Pushery\VisualFeedback\Contracts\AbuseGate;
use Pushery\VisualFeedback\Contracts\ReportChannel;

/**
 * The package's public manager — the target of the `VisualFeedback` facade. Its job in v1 is the
 * channel extension point: `VisualFeedback::extend('slack', fn () => new SlackChannel(...))`
 * registers a custom delivery channel that then rides the normal submit → dispatch flow.
 *
 * Channels for issue trackers and chat tools are deliberately not v1 scope — the way
 * to add them is exactly this extend() seam, not a hardcoded special case.
 */
final readonly class VisualFeedback
{
    /**
     * The texts of the two style blocks a capture writes into its copy of the page: the rule that
     * holds animations and transitions still while the copy is drawn, and the renderer's rule that
     * hides the real pseudo-elements while it draws its own. Both texts are fixed, so a policy can
     * admit them by hash.
     *
     * @var list<non-empty-string>
     */
    private const array CAPTURE_STYLE_BLOCKS = [
        '*{animation-play-state:paused !important;transition:none !important}',
        ".___html2canvas___pseudoelement_before:before{\n    content: \"\" !important;\n    display: none !important;\n}\n         .___html2canvas___pseudoelement_after:after{\n    content: \"\" !important;\n    display: none !important;\n}",
    ];

    public function __construct(
        private ChannelRegistry $registry,
        private AbuseGateRegistry $abuseGates,
    ) {}

    /**
     * Register a custom delivery channel. The factory runs only when `channels.$key.enabled` is
     * true, so an added-but-disabled channel costs nothing.
     *
     * @param  Closure(): ReportChannel  $factory
     */
    public function extend(string $key, Closure $factory): void
    {
        $this->registry->register($key, $factory);
    }

    /**
     * Register the abuse gate for a driver key — the seam an abuse provider attaches to.
     *
     * `VisualFeedback::extendAbuse('botgate', fn () => new MyBotgateGate(...))` plus
     * `abuse.driver=botgate` is the whole integration. Registering alone activates nothing, and
     * the built-in floor keeps running underneath whatever is registered, so an outage or a
     * misconfiguration in the added gate can never remove protection.
     *
     * @param  Closure(): AbuseGate  $factory
     */
    public function extendAbuse(string $driver, Closure $factory): void
    {
        $this->abuseGates->register($driver, $factory);
    }

    /**
     * The hash sources a Content-Security-Policy that admits inline styles by hash lists in
     * `style-src` for a capture, each quoted as the header writes it.
     *
     * A capture of the DOM stage writes two style blocks into the copy of the page it draws, and
     * the copy is held to the page's policy. Under a nonce they carry the nonce. A policy without
     * one refuses both unless it lists these hashes: animations keep running while the copy is
     * drawn, and the renderer cannot hide the real pseudo-elements while it draws its own.
     *
     * @return list<non-empty-string>
     */
    public function cspStyleHashes(): array
    {
        return array_map(
            static fn (string $block): string => "'sha256-".base64_encode(hash('sha256', $block, true))."'",
            self::CAPTURE_STYLE_BLOCKS,
        );
    }
}
