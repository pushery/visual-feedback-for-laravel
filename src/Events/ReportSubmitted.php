<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Events;

use Pushery\VisualFeedback\Data\Report;

/**
 * Dispatched after the report was handed to its channels, each of which queued its own job: a
 * listener runs after the deliveries were started, and under a sync queue after they finished.
 * The hook before the channels is `ReportSubmitting`, where a synchronous listener can still
 * reject the report. It carries the immutable, queue-serialization-safe Report value object.
 */
final readonly class ReportSubmitted
{
    public function __construct(public Report $report) {}
}
