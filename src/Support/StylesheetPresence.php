<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * Whether the host's layout rendered the widget stylesheet during this request.
 *
 * The delivery path has no failure signal of its own. A layout that carries the widget and the
 * scripts tag but not `@include('visual-feedback::style')` ships it unstyled — no positioning
 * for the floating panel, no dialog styling, and no concealment rule for the honeypot, which
 * lives in that sheet.
 *
 * Nothing fails. The widget renders, opens and sends; it simply looks like nothing else on
 * the page. A defect that raises no signal is not fixed, it is got used to.
 *
 * The state lives for one request or one queued job rather than for the process, on purpose:
 * this is an observation about one rendered document, and an instance that outlives it would
 * carry the answer from a page that included the sheet into one that did not. So the class is
 * bound `scoped`. PHP-FPM builds a fresh container for every request; Octane runs each request
 * in a copy of the booted application and forgets scoped instances between requests; a queue
 * worker forgets them between two jobs. A plain singleton survives the last two.
 */
final class StylesheetPresence
{
    private bool $rendered = false;

    /**
     * Called by the stylesheet partial itself, outside its tree branch.
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
