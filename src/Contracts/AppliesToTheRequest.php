<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Contracts;

/**
 * A channel that takes the reports of some requests only, such as those of one tenant, host or shop.
 *
 * The registry asks a channel that implements it before `isAvailable()`. Answering false skips the
 * channel for this report with a debug line and no warning, because nothing is missing: the channel
 * does not apply here. `isAvailable()` keeps its meaning, a channel that applies and cannot deliver
 * because its settings are incomplete, and keeps its warning.
 */
interface AppliesToTheRequest
{
    /** Whether this channel takes the report of the request being handled now. */
    public function appliesToThisRequest(): bool;
}
