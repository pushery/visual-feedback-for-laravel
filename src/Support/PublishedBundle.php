<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Psr\Log\LoggerInterface;

/**
 * Whether the host's published copy of the capture bundle still matches the shipped one.
 *
 * Forgetting `vendor:publish --tag=visual-feedback-assets --force` after an upgrade is the one
 * bug this setup can produce, and until now it was the only one with no signal at all: the copy
 * in `public/` keeps working, keeps being served, and is simply the previous release. Nothing
 * goes red, and the fix arrives as a bug report about behavior that was fixed weeks ago.
 *
 * The check is SERVER-side on purpose, and that is the whole design decision. Any client-side
 * stamp would be executed BY the stale copy — so the one moment it matters, the first request
 * after an upgrade, is exactly the moment it cannot speak. Only the server runs the new version.
 *
 * Not `readonly`, unlike its sibling `Settings`: it memoizes its own measurement, because two
 * `<x-visual-feedback::scripts />` tags on one page would otherwise hash the same two files
 * twice. It is bound as a singleton for the same reason.
 */
final class PublishedBundle
{
    /** @var array<string, ?string> */
    private array $integrity = [];

    /**
     * The IIFE builds — the two files that go through `public/`.
     *
     * The ESM builds are imported straight out of `vendor/` by a consumer's own bundler, so they
     * cannot go stale in this sense; the html2canvas chunk is loaded by the IIFE relative to
     * itself. Both are checked because a publish that copied one and not the other is exactly
     * the half-done state this exists to name.
     */
    private const array BUNDLES = ['visual-feedback-widget.iife.js', 'visual-feedback.iife.js'];

    private ?PublishedBundleStatus $status = null;

    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function status(): PublishedBundleStatus
    {
        return $this->status ??= $this->measure();
    }

    /** A line an operator can act on, for `php artisan about`. */
    public function label(): string
    {
        return match ($this->status()) {
            PublishedBundleStatus::Current => 'up to date',
            PublishedBundleStatus::Stale => 'OUT OF DATE — run: php artisan vendor:publish --tag=visual-feedback-assets --force',
            PublishedBundleStatus::NotPublished => 'not published — run: php artisan vendor:publish --tag=visual-feedback-assets',
            PublishedBundleStatus::ServedExternally => 'served from a configured base URL',
        };
    }

    /**
     * Say something when the published copy cannot do its job.
     *
     * THIS USED TO BE `warnIfStale()` AND IT RETURNED SILENTLY ON THE WORST CASE. `measure()`
     * has always been able to report `NotPublished`, and this method only ever acted on `Stale`
     * — so a host whose `public/vendor/visual-feedback/` was empty got no signal at all, from a
     * package that knew.
     *
     * That is not a cosmetic gap, because of what a missing bundle does under Alpine's CSP
     * build: an unregistered component is an EMPTY SCOPE, not an exception. The markup renders
     * server-side, the panel is drawn, and every directive on it does nothing — no error, no
     * warning, nothing in the console but two 404s that look like an asset-pipeline detail.
     * Measured at a consuming application: four hours to diagnose, and the widget was removed
     * before it was understood.
     *
     * **`NotPublished` is therefore NOT gated on `app.debug`.** The old ordering put the debug
     * check first so production paid one boolean and never touched the filesystem, and that
     * reasoning was right for staleness — a cosmetic mismatch nobody would see. It does not
     * transfer to a widget that is completely inert: production is exactly where this is worth
     * knowing, and it is also where nobody is watching a local log.
     *
     * The cost objection does not survive measurement either. This is a memoized singleton, so
     * it measures once per request however many tags a page carries, and `measure()` returns
     * `NotPublished` on the FIRST missing file — before it hashes anything. Detecting it costs
     * one `is_file()`.
     */
    public function warnIfUnusable(): void
    {
        if ($this->status() === PublishedBundleStatus::NotPublished) {
            $this->logger->error(
                'visual-feedback: the widget bundle is not published, so the widget is INERT — '
                .'its Alpine components are never registered. Under Alpine\'s CSP build that '
                .'fails silently: the panel renders and every control on it does nothing.',
                [
                    'hint' => 'php artisan vendor:publish --tag=visual-feedback-assets',
                    'expected' => $this->publishedPath(self::BUNDLES[0]),
                ],
            );

            return;
        }

        if (! (bool) $this->config->get('app.debug')) {
            return;
        }

        if ($this->status() !== PublishedBundleStatus::Stale) {
            return;
        }

        $this->logger->warning(
            'visual-feedback: the published capture bundle is out of date — the copy in public/ '
            .'is from an earlier release than the one installed.',
            [
                'hint' => 'php artisan vendor:publish --tag=visual-feedback-assets --force',
                'published' => $this->publishedPath(self::BUNDLES[0]),
            ],
        );
    }

    private function measure(): PublishedBundleStatus
    {
        // Asked with the SAME condition `scripts.blade.php` uses to pick an asset base. If the
        // two ever diverge, this would warn about a file the page does not load, or stay quiet
        // about one it does.
        $base = $this->config->get('visual-feedback.ui.assets');

        if (is_string($base) && $base !== '') {
            return PublishedBundleStatus::ServedExternally;
        }

        $stale = false;

        foreach (self::BUNDLES as $bundle) {
            $published = $this->publishedPath($bundle);

            if (! is_file($published)) {
                return PublishedBundleStatus::NotPublished;
            }

            // xxh128 rather than a cryptographic digest: this compares two files a maintainer
            // controls, so speed is the only property that matters and there is nothing to forge.
            $stale = $stale || hash_file('xxh128', $published) !== hash_file('xxh128', dirname(__DIR__, 2).'/dist/'.$bundle);
        }

        return $stale ? PublishedBundleStatus::Stale : PublishedBundleStatus::Current;
    }

    /**
     * The Subresource Integrity digest of a SHIPPED bundle, or null when it cannot be read.
     *
     * Computed from `dist/` inside the package -- the bytes this release actually contains --
     * rather than from whatever a CDN happens to be serving. That is the whole point: the digest
     * is what a divergence would be measured AGAINST, so taking it from the copy under suspicion
     * would prove nothing.
     *
     * WHICH MEANS IT CAN REFUSE A PAGE, AND THAT IS WHY IT IS OPT-IN. A CDN carrying a
     * re-minified or older copy fails the check and the browser drops the script -- the widget
     * then renders and does nothing, which is the failure this package spends most of its guards
     * on. Turning it on is a statement that the CDN mirrors these files byte for byte.
     *
     * `sha384` because that is the SRI middle ground browsers all implement; `xxh128` above is a
     * different job -- comparing two files a maintainer controls, where speed is the only
     * property that matters and there is nothing to forge.
     */
    public function integrity(string $bundle): ?string
    {
        if (! in_array($bundle, self::BUNDLES, true)) {
            return null;
        }

        if (array_key_exists($bundle, $this->integrity)) {
            return $this->integrity[$bundle];
        }

        // No `is_file()` guard, and its absence is the point rather than an omission. `dist/` is a
        // REQUIRED ship directory here -- release.yml refuses to publish without it and
        // DistBundleTest pins every file -- so inside any tree this code can run in, both bundles
        // exist. A branch for their absence is one no test can enter without writing into the real
        // repository to create the state, and an unreachable branch is a coverage floor nobody can
        // meet plus a claim nothing verifies.
        //
        // The failure it guarded against is still handled: a missing file makes hash_file() return
        // false, which is the arm below. The `@` is only there to keep the stream warning out --
        // the suite runs with failOnWarning, so the warning, not the missing digest, would be what
        // turned a run red.
        $digest = @hash_file('sha384', dirname(__DIR__, 2).'/dist/'.$bundle, binary: true);

        return $this->integrity[$bundle] = $digest === false ? null : 'sha384-'.base64_encode($digest);
    }

    private function publishedPath(string $bundle): string
    {
        return $this->app->publicPath('vendor/visual-feedback/'.$bundle);
    }
}
