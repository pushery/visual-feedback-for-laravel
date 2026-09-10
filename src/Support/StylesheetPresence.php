<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * Whether the host's layout rendered the widget stylesheet during this request.
 *
 * THE DELIVERY PATH HAS NO FAILURE SIGNAL OF ITS OWN, AND HALF A FLEET CAN FALL THROUGH IT. In
 * the applications that embed this widget, 6 of 11 carried the widget and the
 * scripts tag but not `@include('visual-feedback::style')`, so they shipped it unstyled — no
 * positioning for the floating panel, no dialog styling, and no concealment rule for the
 * honeypot, which lives in that sheet.
 *
 * Nothing turned red. The widget renders, opens and sends; it simply looks like nothing else on
 * the page. A defect that raises no signal is not fixed, it is got used to.
 *
 * The state is per-request rather than per-process on purpose: this is an observation about ONE
 * rendered document, and a container that outlives the request would carry the answer from a
 * page that included the sheet into one that did not. Laravel gives a fresh container per
 * request, and under Octane the framework resets bound singletons between them.
 */
final class StylesheetPresence
{
    private bool $rendered = false;

    /**
     * Called by the stylesheet partial itself, OUTSIDE its tree branch.
     *
     * The branch decides what the sheet contains, never whether the host asked for it — both
     * trees need the include, and the WireKit branch is smaller rather than empty. Marking
     * inside the branch would report a WireKit host as having forgotten a line it wrote.
     */
    public function markRendered(): void
    {
        $this->rendered = true;
    }

    public function wasRendered(): bool
    {
        return $this->rendered;
    }
}
