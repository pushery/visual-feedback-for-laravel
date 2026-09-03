<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Contracts;

use Pushery\VisualFeedback\Data\Reporter;

/**
 * Resolves the current reporter to a neutral Reporter DTO. The default implementation
 * reads the auth guard; a host app can bind its own to enrich from team / tenant /
 * account context. Guest form fields are passed in for the unauthenticated path.
 */
interface ResolvesReporter
{
    public function resolve(?string $guestName = null, ?string $guestEmail = null, ?string $guestPhone = null): Reporter;
}
