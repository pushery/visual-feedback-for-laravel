<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Events;

/**
 * Dispatched when a channel has FINALLY failed to deliver a report — once, after the
 * channel's retries are exhausted, never per attempt. It carries the exception class
 * and message as STRINGS, never a Throwable: a Throwable in the payload breaks a queued
 * listener at serialization time.
 */
final readonly class ReportDeliveryFailed
{
    public function __construct(
        public string $channel,
        public string $reportUuid,
        public string $exceptionClass,
        public string $message,
    ) {}
}
