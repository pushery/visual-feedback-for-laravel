<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Abuse;

use Illuminate\Contracts\Session\Session;

/**
 * When the reporter opened the form, held on the SERVER and never sent to the browser.
 *
 * The time trap (`abuse.min_fill_seconds`) measures submit-time minus open-time, so the open time
 * is the value an automated submission most wants to move. It used to live on the component as a
 * `#[Locked]` public property, which stopped a client from writing it — and cost more than it
 * bought, in two ways:
 *
 *   * a locked property throws during HYDRATION, before any method of the component runs, and
 *     Livewire's own `wire:navigate` machinery sends the unchanged value back. A widget that sits
 *     in the layout therefore answered ordinary navigation with a 419 the consuming application
 *     could not catch: measured in production at 40 events in 30 days, 16 of them inside 20
 *     seconds from one browser walking the site;
 *   * and the lock only ever stopped the WRITE. The value still traveled to the client in the
 *     snapshot, so it was readable by anyone who looked.
 *
 * Kept here it is neither readable nor writable from the browser, which is a stronger guarantee
 * than the lock gave, and there is no property left for hydration to reject.
 *
 * The session rather than the cache, deliberately. A host on the `array` cache driver — the
 * framework's own test default, and a state a misconfigured deployment reaches — would lose the
 * stamp between the request that opens the form and the one that submits it, and a missing stamp
 * is REFUSED while the trap is armed. That failure would fall on legitimate reporters and look
 * exactly like the abuse it exists to stop.
 */
final readonly class FormOpenedAt
{
    private const string SESSION_KEY = 'visual-feedback.opened_at';

    /**
     * How many widgets are remembered at once.
     *
     * A map rather than a key per widget, because a key per widget grows for the length of a
     * visit: one entry per page view for a widget that is not kept alive by `@persist`. Eight is
     * far more than any page carries and turns unbounded growth into a fixed handful of integers.
     */
    private const int KEEP = 8;

    public function __construct(private Session $session) {}

    /** Anchor the trap for `$widget` at `$at` (a Unix timestamp from the SERVER clock). */
    public function stamp(string $widget, int $at): void
    {
        $stamps = $this->stamps();

        // Re-inserted at the END even when the key is already there, so "recently used" is what
        // survives the cap rather than "first seen" — otherwise the widget a reporter is actually
        // filling in is the one that gets evicted.
        unset($stamps[$widget]);

        $stamps[$widget] = $at;

        $this->session->put(self::SESSION_KEY, array_slice($stamps, -self::KEEP, null, true));
    }

    /**
     * Drop the anchor for `$widget`.
     *
     * A modal is not open at mount, and a stamp left over from an earlier widget in the same
     * session would let it pass a trap it never faced. Absence is a real state here, not an
     * uninitialized one: the abuse gate refuses a submission carrying no open time while the trap
     * is armed, so an unopened modal is refused rather than exempted.
     */
    public function forget(string $widget): void
    {
        $stamps = $this->stamps();

        unset($stamps[$widget]);

        $this->session->put(self::SESSION_KEY, $stamps);
    }

    /** The anchor for `$widget`, or null when it was never opened (or has been evicted). */
    public function for(string $widget): ?int
    {
        $at = $this->stamps()[$widget] ?? null;

        return is_int($at) && $at > 0 ? $at : null;
    }

    /**
     * @return array<string, int>
     */
    private function stamps(): array
    {
        $stamps = $this->session->get(self::SESSION_KEY);

        if (! is_array($stamps)) {
            return [];
        }

        $clean = [];

        foreach ($stamps as $widget => $at) {
            // A session is host storage: another package, a stale serialized value or a hand-edited
            // driver can put anything under this key. Whatever is not a widget id mapped to a
            // timestamp is dropped rather than trusted, because the alternative is a TypeError on a
            // public page.
            if (is_string($widget) && is_int($at)) {
                $clean[$widget] = $at;
            }
        }

        return $clean;
    }
}
