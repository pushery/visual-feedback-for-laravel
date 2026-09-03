<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Bridges;

use MatomoAnalytics\Facades\Matomo;

/**
 * The optional bridge to pushery/matomo-analytics-for-laravel. When that package
 * is installed, an accepted report is tracked as a Matomo event; when it is absent this is a
 * no-op (class_exists on the facade is compile-time safe — the ::class string never autoloads).
 * Kept as a discrete, overridable seam so both branches — package present vs. absent — stay
 * testable even though the package is always present in this repo's own dev tree.
 */
class MatomoBridge
{
    public function isAvailable(): bool
    {
        return class_exists(Matomo::class);
    }

    /** Track an ACCEPTED submission — never a rejection, so bot traffic is not faked into analytics. */
    public function recordSubmission(string $category): void
    {
        Matomo::event('visual-feedback', 'submit', $category);
    }
}
