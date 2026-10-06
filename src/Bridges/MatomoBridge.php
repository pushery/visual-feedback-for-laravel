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
     * Correction. This was written as a bare string with a comment saying an import would make the
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
        // Two questions, and the second one is the one that matters. `class_exists` answers
        // "is the package installed", and the facade class is installed the moment Composer put
        // it in the autoloader. It says nothing about whether the facade can resolve — that
        // depends on the service provider having registered `Tracker`, which is a separate event
        // and one an application can prevent: `extra.laravel.dont-discover`, a hand-written
        // provider list, or any boot order that skips discovery.
        //
        // In an application with the package installed and its provider absent, `class_exists`
        // is true and `bound()` is false. A guard on `class_exists` alone would say "available",
        // the facade would then throw BindingResolutionException — and because this runs inside
        // the ReportSubmitted listener, an analytics gap would take the reporter's submission
        // down with it. That is the wrong failure direction by a wide margin: a lost event is
        // invisible and costs nothing, a lost report is the one thing this package exists to
        // prevent.
        //
        // An application that registers its providers by hand and leaves the analytics
        // package's own out is an ordinary configuration, not an artificial one, which is why
        // the check belongs here.
        return class_exists(Matomo::class)
            && Container::getInstance()->bound(self::TRACKER);
    }

    /** Track an accepted submission — never a rejection, so bot traffic is not faked into analytics. */
    public function recordSubmission(string $category): void
    {
        Matomo::event('visual-feedback', 'submit', $category);
    }
}
