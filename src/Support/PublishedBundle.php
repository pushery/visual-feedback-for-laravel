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
    /**
     * The only one of the three dist/ files that goes through `public/`.
     *
     * The ESM build is imported straight out of `vendor/` by a consumer's own bundler, so it
     * cannot go stale in this sense; the chunk is loaded by the IIFE relative to itself.
     */
    private const string BUNDLE = 'visual-feedback.iife.js';

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
     * Log a warning while the copy is stale — under `app.debug` only.
     *
     * The debug check comes FIRST, and the order is load-bearing rather than tidy: in production
     * this method then costs one boolean read and touches the filesystem not at all. It is
     * called from a Blade view that renders on every page carrying the widget, so a hash of two
     * files per request would be a real cost for a message nobody would see.
     */
    public function warnIfStale(): void
    {
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
                'published' => $this->publishedPath(),
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

        $published = $this->publishedPath();

        if (! is_file($published)) {
            return PublishedBundleStatus::NotPublished;
        }

        $shipped = dirname(__DIR__, 2).'/dist/'.self::BUNDLE;

        // xxh128 rather than a cryptographic digest: this compares two files a maintainer
        // controls, so speed is the only property that matters and there is nothing to forge.
        return hash_file('xxh128', $published) === hash_file('xxh128', $shipped)
            ? PublishedBundleStatus::Current
            : PublishedBundleStatus::Stale;
    }

    private function publishedPath(): string
    {
        return $this->app->publicPath('vendor/visual-feedback/'.self::BUNDLE);
    }
}
