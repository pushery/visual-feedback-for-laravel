<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Bridges;

use Illuminate\Container\Container;
use MatomoAnalytics\Contracts\Tracker;
use MatomoAnalytics\Facades\Matomo;

/**
 * The optional bridge to pushery/matomo-analytics-for-laravel. When that package
 * is installed, an accepted report is tracked as a Matomo event; when it is absent this is a
 * no-op (class_exists on the facade is compile-time safe — the ::class string never autoloads).
 * Kept as a discrete, overridable seam so both branches — package present vs. absent — stay
 * testable even though the package is always present in this repo's own dev tree.
 */
class MatomoBridge
{
    /**
     * The interface the facade resolves through.
     *
     * CORRECTION. This was written as a bare string with a comment saying an import would make the
     * optional dependency a compile-time one. Rector rewrote it, and Rector is right: `use` is an
     * alias resolved at compile time and `::class` is a string literal, so neither autoloads
     * anything. The class is still never touched in a consumer without the package — which is the
     * same guarantee the docblock above already claims for the facade, imported the same way
     * since this file was written.
     *
     * What the string form actually bought was nothing, and what it cost is a reader having to
     * decide which of two contradicting statements in one file to believe.
     */
    private const string TRACKER = Tracker::class;

    public function isAvailable(): bool
    {
        // TWO questions, and the second one is the one that was missing. `class_exists` answers
        // "is the package installed", and the facade class is installed the moment Composer put
        // it in the autoloader. It says nothing about whether the facade can RESOLVE — that
        // depends on the service provider having registered `Tracker`, which is a separate event
        // and one an application can prevent: `extra.laravel.dont-discover`, a hand-written
        // provider list, or any boot order that skips discovery.
        //
        // Measured, not reasoned: in an application with the package installed and its provider
        // absent, `class_exists` is true and `bound()` is false. The old guard therefore said
        // "available", the facade then threw BindingResolutionException — and because this runs
        // inside the ReportSubmitted listener, an ANALYTICS gap took the reporter's submission
        // down with it. That is the wrong failure direction by a wide margin: a lost event is
        // invisible and costs nothing, a lost report is the one thing this package exists to
        // prevent.
        //
        // It surfaced in the dependency-floor lane, whose fixture boots the shipped tree with
        // only this package's own providers registered. That lane reproduces a real consumer
        // configuration rather than an artificial one, which is why the fix belongs here and not
        // in the fixture.
        return class_exists(Matomo::class)
            && Container::getInstance()->bound(self::TRACKER);
    }

    /** Track an ACCEPTED submission — never a rejection, so bot traffic is not faked into analytics. */
    public function recordSubmission(string $category): void
    {
        Matomo::event('visual-feedback', 'submit', $category);
    }
}
