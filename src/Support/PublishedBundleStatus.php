<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * The state of the host's published copy of the capture bundle, relative to the shipped one.
 *
 * Four states rather than a boolean, because "not published" and "out of date" want opposite
 * reactions from an operator and `served externally` wants none at all — collapsing them would
 * put a false alarm in front of every host that serves the bundle from a CDN.
 */
enum PublishedBundleStatus: string
{
    case Current = 'current';
    case Stale = 'stale';
    case NotPublished = 'not-published';
    case ServedExternally = 'served-externally';
}
