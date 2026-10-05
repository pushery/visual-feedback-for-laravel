<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Privacy;

use RuntimeException;

/**
 * Why a channel withheld a report: its reporter was erased after submitting it.
 *
 * It is never thrown. A channel settles with it, so `ReportDeliveryFailed` carries this class as
 * its `exceptionClass`, and a listener can tell a withheld delivery from one that failed.
 */
final class ReporterWasErased extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('visual-feedback: the reporter was erased after submitting this report, so this delivery was withheld.');
    }
}
