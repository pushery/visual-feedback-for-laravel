<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Events;

/**
 * Dispatched after a screenshot has passed server-side validation and been stored,
 * carrying the report UUID and the stored path. Strings only — queue-listener-safe.
 */
final readonly class ScreenshotAttached
{
    public function __construct(
        public string $reportUuid,
        public string $path,
    ) {}
}
