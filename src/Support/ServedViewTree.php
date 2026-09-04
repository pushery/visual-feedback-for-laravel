<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\View\Factory as ViewFactory;
use InvalidArgumentException;

/**
 * Which view tree is ACTUALLY rendering — resolved, not configured.
 *
 * `Settings::servesWireKitViews()` answers a different question, and the difference is the whole
 * reason this class exists. It says which tree the package ASKED for; this one says which tree
 * the view finder HANDS BACK. They disagree in a case the documented install produces:
 *
 *   `vendor:publish --tag=visual-feedback` copies all of resources/views into the host's
 *   resources/views/vendor/visual-feedback. Laravel registers that path AHEAD of every path a
 *   package passes to loadViewsFrom, and the finder returns the first match — so the published
 *   PLAIN template serves while `ui.variant` still says `wirekit`.
 *
 * A published copy winning is correct and deliberate: it is what makes publishing the way to
 * EDIT the templates. What was not correct was the stylesheet reading the config answer and
 * silencing itself over a plain widget, which left the documented happy path with an
 * unpositioned FAB, an unstyled dialog, and a honeypot whose concealment rule lives in that
 * stylesheet.
 */
final readonly class ServedViewTree
{
    // The CONCRETE factory, not the contract: `getFinder()` is not on the interface, and
    // guarding a call the contract does not promise would add a branch no run can enter — the
    // container binds this class either way.
    public function __construct(private ViewFactory $views) {}

    /**
     * Is the template the finder resolves for the widget the WireKit one?
     *
     * Anchored on the widget rather than on any smaller partial because it is the view whose
     * tree decides how everything around it must be styled, and it is the one a host publishes
     * when they publish anything at all.
     */
    public function servingWireKit(): bool
    {
        try {
            $resolved = $this->views->getFinder()->find('visual-feedback::livewire.report-widget');
        } catch (InvalidArgumentException) {
            // No template at all. Whatever else is wrong, silencing the stylesheet cannot help,
            // and rendering it costs a host nothing but a few unused custom properties. Every
            // uncertain branch here leans the same way on purpose: a widget with CSS it does not
            // need is a cosmetic waste, a widget with no CSS is unusable.
            return false;
        }

        // Read the TEMPLATE, not its path. A path check answers "did this come out of the
        // package's wirekit directory", and that is the wrong question for the case the
        // `visual-feedback-wirekit` tag produces: it copies the WireKit widget to the SAME
        // vendor path the plain one would occupy, so a host who published the WireKit tree
        // would be told they are on the plain one and get the plain stylesheet over it — this
        // defect's mirror image.
        //
        // ⚠️ THE MARKER IS THE TREE'S OWN COMPOSITION, NOT "does WireKit appear anywhere".
        // The obvious test is `x-wirekit::`, and it reads as safe because the plain widget is
        // framework-free and carries none of them. But this package tells hosts to publish these
        // templates in order to EDIT them — so a host on the plain tree who drops a single
        // `<x-wirekit::icon>` into their published copy would flip this to true and lose the
        // whole stylesheet: no positioning, no dialog styling, no concealment rule for the
        // honeypot. That is the same failure this class was written to end, arriving from the
        // other side.
        //
        // The marker is the WireKit widget's include of this package's own shared form partial.
        // It is structural — the tree is built that way and the plain tree never references it —
        // and it is not something a host adds by decorating a form.
        //
        // Written WITHOUT its `visual-feedback::` namespace prefix on purpose. With it, the
        // string is a well-formed view name, and `ShipTreeResolvesWithinItselfTest` reads shipped
        // code for view names and checks each one resolves — so a marker that merely looks like a
        // reference gets reported as a view the release would leave behind. The suffix is just as
        // specific and is not a name anything tries to resolve.
        $template = @file_get_contents($resolved);

        return is_string($template) && str_contains($template, 'wirekit.livewire._report-form');
    }
}
