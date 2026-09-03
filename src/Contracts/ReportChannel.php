<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Contracts;

use Pushery\VisualFeedback\Data\Report;

/**
 * A delivery channel for a submitted report. Channels are an OPEN, configurable contract — not
 * three hardcoded special cases: the built-in mail/database/webhook channels implement this, and
 * a consumer adds their own via VisualFeedback::extend(). Each channel queues its OWN job, so one
 * channel's failure or retry can never affect another's, and `dispatch()` enqueues work rather
 * than delivering inline.
 */
interface ReportChannel
{
    /** The config key this channel is enabled/tuned under (`channels.<key>`). */
    public function key(): string;

    /**
     * Whether this channel can run in the current app — e.g. an optional dependency is installed,
     * or the required config is present. A channel that is enabled but not available is skipped,
     * never dispatched (so a misconfigured channel never wedges delivery).
     */
    public function isAvailable(): bool;

    /** Enqueue this channel's own queued job to deliver the report. Never delivers inline. */
    public function dispatch(Report $report): void;
}
