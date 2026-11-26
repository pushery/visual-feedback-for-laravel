<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * The state of the host's published copy of the capture bundle, relative to the shipped one.
 *
 * Five states rather than a boolean, because "not published" and "out of date" want opposite
 * reactions from an operator, and `served externally` and `served by route` want none at all:
 * collapsing them would put a false alarm in front of every host that serves the bundle from a CDN
 * or lets the package's own route serve it.
 */
enum PublishedBundleStatus: string
{
    case Current = 'current';
    case Stale = 'stale';
    case NotPublished = 'not-published';
    case ServedExternally = 'served-externally';
    case ServedByRoute = 'served-by-route';
}
