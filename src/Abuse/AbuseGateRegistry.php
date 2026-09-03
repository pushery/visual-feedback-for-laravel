<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Abuse;

use Closure;
use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Contracts\AbuseGate;
use Pushery\VisualFeedback\Support\Settings;

/**
 * Where an additional abuse driver attaches — the registration point the composite was missing.
 *
 * `abuse.driver` was documented as "selects the gate" and selected nothing: the provider handed
 * AbuseGateManager a hard-coded empty list, and `Settings::abuseDriver()` had no caller anywhere in
 * src/. So a `driver=botgate` install ran on the floor alone, silently, exactly as a `driver=typo`
 * install did. This closes that: the configured driver is looked up here, and a
 * driver that names no registered gate says so in the log instead of disappearing.
 *
 * Deliberately the same shape as ChannelRegistry — a Closure FACTORY per key, instantiated only
 * when the configuration actually asks for it, so a registered-but-unselected driver costs nothing
 * and cannot touch a service it does not need.
 *
 * The floor is NOT in here and never will be. BuiltinAbuseGate runs unconditionally underneath
 * whatever this returns, which is why an empty return is always safe: it means the
 * floor alone, never no protection.
 */
final class AbuseGateRegistry
{
    /** @var array<string, Closure(): AbuseGate> */
    private array $factories = [];

    public function __construct(
        private readonly Settings $settings,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Register the gate for a driver key. Registering does not activate it — `abuse.driver` does.
     *
     * @param  Closure(): AbuseGate  $factory
     */
    public function register(string $driver, Closure $factory): void
    {
        $this->factories[$driver] = $factory;
    }

    /** Whether a gate is registered under this key, without building it. */
    public function has(string $driver): bool
    {
        return isset($this->factories[$driver]);
    }

    /**
     * The gates to layer on top of the floor for the configured driver.
     *
     * `builtin` and `none` both mean "the floor alone" — the floor cannot be switched off, so
     * neither value can remove protection; `none` is the explicit way to decline an additional
     * driver even when one is registered.
     *
     * @return list<AbuseGate>
     */
    public function additional(): array
    {
        $driver = $this->settings->abuseDriver();

        if ($driver === 'builtin' || $driver === 'none') {
            return [];
        }

        $factory = $this->factories[$driver] ?? null;

        if ($factory === null) {
            // Loud, because the alternative is what this class exists to end: a configured driver
            // that protects nothing and looks configured. The floor still carries the request.
            $this->logger->warning('visual-feedback: no abuse gate is registered for the configured driver — the built-in floor is running alone', [
                'driver' => $driver,
                'registered' => array_keys($this->factories),
            ]);

            return [];
        }

        return [$factory()];
    }
}
