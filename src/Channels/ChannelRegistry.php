<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Contracts\ReportChannel;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Support\EnvFlag;
use Pushery\VisualFeedback\Support\RedactedFailure;
use Throwable;

/**
 * The registry of delivery channels. Channels are registered as FACTORIES (built-in ones by the
 * provider, custom ones via VisualFeedback::extend()), and a factory is invoked ONLY when its
 * config key is enabled — a disabled channel is never instantiated, so it costs nothing at boot.
 * An enabled-but-unavailable channel (missing dependency/config) is skipped, never dispatched.
 *
 * The registry is a singleton a host fills once, at boot, through extend(), so it holds the
 * factories and nothing else. The configuration, the delivery tracker and the logger are asked of
 * the container serving the current request, each time they are needed. Held from the first
 * resolution they would be that moment's instances: under Octane a configuration a request
 * changes would go unseen, and a host's Event::fake() or Log::spy() would miss the failure this
 * registry reports.
 */
final class ChannelRegistry
{
    /** The channels the shipped config switches on, so a missing switch reads the same. */
    private const array ENABLED_BY_DEFAULT = ['mail' => true];

    /** @var array<string, Closure(): ReportChannel> */
    private array $factories = [];

    /**
     * Register a channel factory under `$key`. Lazy: the factory runs only if `channels.$key`
     * is enabled. Re-registering a key overrides it (so extend() can replace a built-in).
     *
     * @param  Closure(): ReportChannel  $factory
     */
    public function register(string $key, Closure $factory): void
    {
        $this->factories[$key] = $factory;
    }

    /**
     * The enabled AND available channels, instantiated. A disabled channel is never instantiated;
     * an unavailable one is dropped after construction — and SAID SO, which it was not.
     *
     * A channel that is switched on and then silently dropped is the difference between "you
     * turned it off" and "you turned it on and it does not work", and those want opposite
     * reactions. The default installation is exactly the second: `channels.mail.enabled` ships
     * true and `mail.to` ships empty, so the very first report a new consumer sends reaches
     * nobody, with nothing in the log to say why.
     *
     * @return list<ReportChannel>
     */
    public function channels(): array
    {
        $logger = app(LoggerInterface::class);
        $channels = [];

        foreach ($this->factories as $key => $factory) {
            if (! $this->isEnabled($key)) {
                continue; // no instantiation for a disabled channel
            }

            // Construction and the availability probe are INSIDE the isolation, and they were
            // not. Both run consumer code — a factory registered through extend(), and that
            // channel's own isAvailable() — so a single broken custom channel used to throw out
            // of here and take the whole report with it, past the per-channel try/catch in
            // dispatch() that exists to stop precisely that.
            try {
                $channel = $factory();

                if ($channel->isAvailable()) {
                    $channels[] = $channel;

                    continue;
                }

                $logger->warning('visual-feedback: an enabled channel reported itself unavailable and was skipped', [
                    'channel' => $key,
                    'hint' => 'the channel is switched on but its configuration is incomplete — a missing recipient, URL or dependency',
                ]);
            } catch (Throwable $exception) {
                $logger->error('visual-feedback: a channel could not be constructed and was skipped', [
                    'channel' => $key,
                    'exception' => RedactedFailure::classOf($exception),
                    'message' => RedactedFailure::message($exception),
                ]);
            }
        }

        return $channels;
    }

    /**
     * Dispatch the report to every enabled + available channel. Each channel is isolated: a
     * dispatch-time failure of one is logged and settled as a terminal failure for THAT channel
     * (via the tracker), and never blocks the others. The lifecycle is armed once, up front, so
     * the attachment refcount / retain policy / zero-channels cleanup are decided from the full
     * channel set.
     *
     * Returns how many channels took the report, and that number is the point rather than a
     * convenience: the caller could not previously tell "handed to somebody" from "accepted and
     * dropped", so both ended at the same success state in the widget while only one of them was
     * true. A dispatch-time throw still counts — the report reached that channel and its receipt
     * settles FAILED, which is a trail rather than a silence.
     */
    public function dispatch(Report $report): int
    {
        $channels = $this->channels();
        $logger = app(LoggerInterface::class);
        $tracker = app(ReportDeliveryTracker::class);

        if ($channels === []) {
            // The report is accepted, stored, and delivered nowhere. Every individual reason for
            // that is now logged above, but the SUM is worth its own line: an operator reading
            // "mail skipped" learns one channel is misconfigured, while this says the report is
            // gone. The tracker still runs its zero-channel cleanup — the attachments are
            // released rather than orphaned — so this is the only trace there will be.
            $logger->warning('visual-feedback: a report was accepted but no channel was enabled and available to deliver it', [
                'report' => $report->id,
                'registered' => array_keys($this->factories),
            ]);
        }

        $tracker->begin($report, $channels);

        foreach ($channels as $channel) {
            try {
                $channel->dispatch($report);
            } catch (Throwable $exception) {
                $logger->error('visual-feedback: channel dispatch failed', [
                    'channel' => $channel->key(),
                    'report' => $report->id,
                    'exception' => RedactedFailure::classOf($exception),
                    'message' => RedactedFailure::message($exception),
                ]);

                $tracker->settleFailed($report, $channel->key(), $exception);
            }
        }

        return count($channels);
    }

    /**
     * Whether a channel is switched on. A channel the configuration does not mention at all, as a
     * config cached before an upgrade can leave one, takes the default the shipped file gives it:
     * on for mail, off for every other channel, a host's own included.
     */
    private function isEnabled(string $key): bool
    {
        return EnvFlag::boolean(
            app(Repository::class)->get("visual-feedback.channels.{$key}.enabled"),
            self::ENABLED_BY_DEFAULT[$key] ?? false,
        );
    }
}
