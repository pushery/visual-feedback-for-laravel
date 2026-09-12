<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Pushery\VisualFeedback\Contracts\ResolvesReporter;

/**
 * Whether this request may see the widget at all — the master switch and the sign-in switch in
 * one question, because every caller wants both and none of them wants to remember that.
 *
 * There are five component templates across two view trees plus the component's own render(), and
 * before this they each asked `Settings::enabled()` directly. Adding a second condition to six
 * places is how one of them ends up with only the first — and the one that gets missed renders a
 * button that opens a form the server will refuse, which is worse than either state on its own.
 *
 * ALL OF THIS IS THE DRAWING HALF, and none of it is the enforcement. A Livewire component is
 * registered by name and is therefore reachable without the page that renders it, so the switch
 * that holds is in SubmitReport::handle(). This class exists so the reporter is not shown a door
 * that is already locked; it is not what locks it.
 */
final readonly class WidgetAvailability
{
    public function __construct(
        private Settings $settings,
        private ResolvesReporter $reporter,
    ) {}

    public function forThisRequest(): bool
    {
        if (! $this->settings->enabled()) {
            return false;
        }

        if (! $this->settings->requiresAuthentication()) {
            return true;
        }

        // ASKED THROUGH ResolvesReporter RATHER THAN THE AUTH FACADE, and that is also why this
        // switch ships without a guard-name key of its own. That contract is already this
        // package's one answer to "who is reporting", and a host on a non-default guard has to
        // bind their own resolver for the reporter's IDENTITY to be right at all. A separate
        // `authentication_guard` setting would therefore only ever be consulted by installs whose
        // reporter is already resolved by something else — two sources of truth that disagree,
        // with the package picking the one that knows less.
        return ! $this->reporter->resolve()->isGuest;
    }
}
