<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels\Webhook;

use Pushery\Webhooks\Facades\Webhooks;

/**
 * The thin bridge to the OPTIONAL pushery/webhooks-for-laravel platform.
 * When that package is installed, a report is fanned out THROUGH it — fleet infrastructure
 * that owns signing, retries, de-duplication and a delivery dashboard — instead of the
 * built-in signed HTTP fallback. `class_exists()` on the facade is compile-time safe: in a
 * consumer without the package, `Webhooks::class` resolves to the bare string and no
 * autoload fires, so `isInstalled()` is simply false and `dispatch()` is never reached.
 *
 * `tenant: null` reaches only GLOBAL subscriptions — a host lists the event in its
 * `webhooks.platform.catalog` for its management UI, and a multi-tenant host that wants
 * per-tenant fan-out registers its own channel via VisualFeedback::extend() and passes the
 * tenant model. This class is intentionally NOT final: it is the seam that lets both
 * branches (platform present vs. absent) be exercised even though the package is always
 * present in this repo's own dev tree.
 */
class WebhooksPlatform
{
    /** The event type hosts subscribe to (and declare in webhooks.platform.catalog). */
    public const string EVENT_TYPE = 'visual_feedback.report.created';

    public function isInstalled(): bool
    {
        return class_exists(Webhooks::class);
    }

    /**
     * Fan the report out through the platform and return HOW MANY subscriptions it reached.
     *
     * The platform returns a Collection of deliveries and this method used to be typed `: void`,
     * throwing it away. The caller then settled the report DELIVERED regardless — so "the
     * platform accepted this and sent it to three endpoints" and "nobody is subscribed to this
     * event type" produced an identical, positive receipt. Zero is the state a host actually
     * lands in: the event has to be listed in `webhooks.platform.catalog` AND something has to
     * subscribe to it, and a fresh installation has neither.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(array $payload): int
    {
        return Webhooks::dispatch(self::EVENT_TYPE, $payload)->count();
    }
}
