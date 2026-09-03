<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Events;

/**
 * Dispatched once a channel has terminally delivered a report. One event per channel,
 * keyed by the report UUID — strings only, so queued listeners stay safe.
 */
final readonly class ReportDelivered
{
    public function __construct(
        public string $channel,
        public string $reportUuid,
    ) {}
}
