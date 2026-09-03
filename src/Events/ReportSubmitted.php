<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Events;

use Pushery\VisualFeedback\Data\Report;

/**
 * Dispatched after a report is accepted, before it is dispatched to any channel. It
 * carries the immutable, queue-serialization-safe Report value object.
 */
final readonly class ReportSubmitted
{
    public function __construct(public Report $report) {}
}
