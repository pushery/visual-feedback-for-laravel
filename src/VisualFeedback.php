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
 * Channels for issue trackers and chat tools are deliberately NOT v1 scope — the way
 * to add them is exactly this extend() seam, not a hardcoded special case.
 */
final readonly class VisualFeedback
{
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
}
