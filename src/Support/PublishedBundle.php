<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Composer\InstalledVersions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Psr\Log\LoggerInterface;

/**
 * Whether the host's published copy of the capture bundle still matches the shipped one.
 *
 * Forgetting `vendor:publish --tag=visual-feedback-assets --force` after an upgrade is the one
 * bug this setup can produce, and without this check it has no signal at all: the copy in
 * `public/` keeps working, keeps being served, and is simply the previous release. Nothing
 * fails, and the fix arrives as a bug report about behavior that was fixed weeks ago.
 *
 * The check is server-side on purpose, and that is the whole design decision. Any client-side
 * stamp would be executed by the stale copy — so the one moment it matters, the first request
 * after an upgrade, is exactly the moment it cannot speak. Only the server runs the new version.
 *
 * Not `readonly`, unlike its sibling `Settings`: it memoizes its own measurement, because two
 * `<x-visual-feedback::scripts />` tags on one page would otherwise hash the same two files
 * twice. It is bound scoped for the same reason, so the measurement is made once per request or
 * queued job, and a long-running worker measures a re-published copy anew.
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

    /**
     * Hashable, but deliberately not a bundle.
     *
     * No `<script>` tag renders it -- the capture bundle appends it at capture time -- so it takes
     * part in no staleness comparison and has no published path of its own to report. It does need
     * a digest, because it is fetched from the same foreign origin as the two that have one, and
     * it is by far the largest of the three.
     */
    private const string RENDERER = 'visual-feedback-renderer.iife.js';

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
     * It acts on `NotPublished` as well as on `Stale`. Acting on `Stale` alone would return
     * silently on the worst case: a host whose `public/vendor/visual-feedback/` is empty would get
     * no signal at all, from a package that knows.
     *
     * That is not a cosmetic gap, because of what a missing bundle does under Alpine's CSP
     * build: an unregistered component is an empty scope, not an exception. The markup renders
     * server-side, the panel is drawn, and every directive on it does nothing — no error, no
     * warning, nothing in the console but two 404s that look like an asset-pipeline detail.
     * Nothing in that picture points at the publish step.
     *
     * **`NotPublished` is therefore not gated on `app.debug`.** Checking the debug flag first would
     * let production pay one boolean and never touch the filesystem, and that reasoning is right
     * for staleness — a cosmetic mismatch nobody would see. It does not
     * transfer to a widget that is completely inert: production is exactly where this is worth
     * knowing, and it is also where nobody is watching a local log.
     *
     * The cost objection does not survive measurement either. This is a memoized singleton, so
     * it measures once per request however many tags a page carries, and `measure()` returns
     * `NotPublished` on the first missing file — before it hashes anything. Detecting it costs
     * one `is_file()`.
     */
    public function warnIfUnusable(): void
    {
        if ($this->status() === PublishedBundleStatus::NotPublished) {
            $this->logger->error(
                'visual-feedback: the widget bundle is not published, so the widget is INERT — '
                .'its Alpine components are never registered. The panel renders and every control '
                .'on it does nothing, and the browser reports it only in its console.',
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

    /**
     * The cache-busting token for one bundle's URL.
     *
     * It is the content hash of the published file, not the package version, and that is the
     * correction rather than an optimization. The version was read from
     * `Composer\InstalledVersions`, which answers about `vendor/` — and a consuming application
     * reported `?id=v0.4.1` on a page where v0.5.0 was installed. However that host got there, the
     * shape of the mistake is the point: the token described the package while the bytes being
     * served came from `public/`, and those two are exactly what a republish is supposed to
     * reconcile. A hash of the file cannot disagree with the file.
     *
     * It also closes a case the version never covered: a re-publish that changes `dist/` without a
     * version bump left the URL standing, so a browser kept the old copy.
     *
     * Costs nothing extra. `measure()` already hashes both published bundles on every request —
     * `warnIfUnusable()` calls `status()` unconditionally so that a missing publish is reported in
     * production — and this is a memoized singleton, so a page with two script tags measures once.
     *
     * Falls back to the package version where there is no local file to hash: a configured assets
     * base URL (the bytes are somebody else's) and an unpublished install (already reported as an
     * error). Neither can be answered by hashing, and inventing a token there would move the URL
     * for no reason.
     *
     * The renderer has a token too, although no tag loads it: the capture bundle appends it at
     * capture time from a URL that would otherwise carry no token at all, so a browser that cached
     * the renderer once would pair that copy with every later capture bundle. The capture tag
     * hands the token over as `data-renderer-token`. The renderer's own hash costs one more read
     * of the file, and only on a page that renders the capture tag.
     */
    public function cacheToken(string $bundle): string
    {
        if (! in_array($bundle, self::BUNDLES, true) && $bundle !== self::RENDERER) {
            return $this->packageVersion();
        }

        if ($this->status() !== PublishedBundleStatus::Current && $this->status() !== PublishedBundleStatus::Stale) {
            return $this->packageVersion();
        }

        $hash = @hash_file('xxh128', $this->publishedPath($bundle));

        return $hash === false ? $this->packageVersion() : $hash;
    }

    /**
     * The installed version, as Composer reports it.
     *
     * Moved out of `scripts.blade.php` so the whole token decision sits in one testable place —
     * a template is where a rule goes to stop being checkable.
     *
     * Public, and it takes the package name, for the same reason
     * `VisualFeedbackServiceProvider::packageSatisfiesWireKitFloor()` does: the not-installed
     * branch is unreachable for a package that is installed by definition, and under a 100%
     * coverage floor an unreachable line is permanently red while reading as a missing test. A
     * caller passing a name that is genuinely absent exercises it honestly. The default is the
     * only name this package ever asks about.
     *
     * The `class_exists` check that used to sit beside it in the template is gone rather than
     * hidden: this package is installed by Composer, so Composer's own runtime class is present by
     * construction. It was a guard against a state that cannot occur — free in a Blade file, which
     * no coverage report reads, and dead weight the moment the rule moved somewhere checkable.
     */
    public function packageVersion(string $package = 'pushery/visual-feedback-for-laravel'): string
    {
        if (! InstalledVersions::isInstalled($package)) {
            return 'dev';
        }

        $version = (string) (InstalledVersions::getPrettyVersion($package) ?: 'dev');
        $reference = InstalledVersions::getReference($package);

        // A branch install keeps its version string across every update, so there the resolved
        // commit is what actually moves.
        return is_string($reference) && str_starts_with($version, 'dev-')
            ? $version.'.'.substr($reference, 0, 8)
            : $version;
    }

    private function measure(): PublishedBundleStatus
    {
        // Asked with the same condition `scripts.blade.php` uses to pick an asset base. If the
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
     * The Subresource Integrity digest of a shipped bundle, or null when it cannot be read.
     *
     * Computed from `dist/` inside the package -- the bytes this release actually contains --
     * rather than from whatever a CDN happens to be serving. That is the whole point: the digest
     * is what a divergence would be measured against, so taking it from the copy under suspicion
     * would prove nothing.
     *
     * Which means it can refuse a page, and that is why it is opt-in. A CDN carrying a
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
        if (! in_array($bundle, self::BUNDLES, true) && $bundle !== self::RENDERER) {
            return null;
        }

        if (array_key_exists($bundle, $this->integrity)) {
            return $this->integrity[$bundle];
        }

        // No `is_file()` guard, and its absence is the point rather than an omission. `dist/` is
        // part of every release of this package, so inside any tree this code can run in, both
        // bundles exist, and a branch for their absence could never be entered.
        //
        // A missing file is still handled: hash_file() returns false, which is the arm below. The
        // `@` keeps the stream warning out, which Laravel's error handler would otherwise turn
        // into an exception before the false ever arrived.
        $digest = @hash_file('sha384', dirname(__DIR__, 2).'/dist/'.$bundle, binary: true);

        return $this->integrity[$bundle] = $digest === false ? null : 'sha384-'.base64_encode($digest);
    }

    private function publishedPath(string $bundle): string
    {
        return $this->app->publicPath('vendor/visual-feedback/'.$bundle);
    }
}
