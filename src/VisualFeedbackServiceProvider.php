<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback;

use Composer\InstalledVersions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Override;
use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Abuse\AbuseGateManager;
use Pushery\VisualFeedback\Abuse\AbuseGateRegistry;
use Pushery\VisualFeedback\Abuse\BuiltinAbuseGate;
use Pushery\VisualFeedback\Channels\ChannelRegistry;
use Pushery\VisualFeedback\Channels\DatabaseChannel;
use Pushery\VisualFeedback\Channels\MailChannel;
use Pushery\VisualFeedback\Channels\ReceiptStore;
use Pushery\VisualFeedback\Channels\WebhookChannel;
use Pushery\VisualFeedback\Console\ForgetReporter;
use Pushery\VisualFeedback\Console\PruneReports;
use Pushery\VisualFeedback\Console\SweepOrphanAttachments;
use Pushery\VisualFeedback\Context\ContextRegistry;
use Pushery\VisualFeedback\Contracts\AbuseGate;
use Pushery\VisualFeedback\Contracts\ReportChannel;
use Pushery\VisualFeedback\Contracts\ResolvesReporter;
use Pushery\VisualFeedback\Events\ReportSubmitted;
use Pushery\VisualFeedback\Listeners\TrackReportSubmission;
use Pushery\VisualFeedback\Livewire\ReportWidget;
use Pushery\VisualFeedback\Reporter\GuardReporterResolver;
use Pushery\VisualFeedback\Support\CategoryLabels;
use Pushery\VisualFeedback\Support\PublishedBundle;
use Pushery\VisualFeedback\Support\Settings;

final class VisualFeedbackServiceProvider extends ServiceProvider
{
    /**
     * The WireKit release that first carried what the WireKit tree binds to.
     *
     * 2.21 introduced the `placement` prop and the accessible-name path `<x-wirekit::fab.button>`
     * needs. Serving the tree against anything older renders a trigger with no accessible name,
     * which is worse than not serving it at all — so `auto` refuses below that line.
     */
    public const string WIREKIT_MINIMUM = '2.21.0';

    /**
     * Whether an installed WireKit is new enough for the tree this package ships.
     *
     * Split from the check itself so a test can drive the comparison without installing four
     * versions of a package. A null version means "not installed", which is a no.
     */
    public static function wireKitVersionSatisfies(?string $version): bool
    {
        if (! is_string($version)) {
            return false;
        }

        // The leading `v` has to go, and it is not cosmetic. Composer's getPrettyVersion returns
        // the tag as written — `v2.42.0` for this package's own vendor — and version_compare
        // reads a leading letter as a pre-release marker, so `v2.42.0` compares BELOW `2.21.0`.
        // Measured here on the real vendor tree: the check said no while WireKit 2.42 was
        // installed, which would have served the plain tree to every host that has WireKit.
        return version_compare(ltrim($version, 'vV'), self::WIREKIT_MINIMUM, '>=');
    }

    /**
     * The view paths, in resolution ORDER, for the tree the settings select.
     *
     * A named method rather than an inline ternary so the order can be asserted directly. It has
     * to be asserted directly, because asserting it through a second boot() does not work:
     * Laravel's loadViewsFrom APPENDS to a namespace's hints rather than replacing them, so a
     * test that boots twice reads the paths of the first boot with the second appended — and
     * reports the wrong tree for the right reason.
     *
     * @return list<string>
     */
    public static function viewPaths(Settings $settings): array
    {
        $plain = __DIR__.'/../resources/views';

        // WireKit FIRST when it is served: the finder walks these in turn, so a view that exists
        // in both trees resolves to the WireKit one while everything that exists only in the
        // plain tree keeps working untouched.
        return $settings->servesWireKitViews()
            ? [$plain.'/wirekit', $plain]
            : [$plain];
    }

    /**
     * Whether a package is installed at a version this package's WireKit tree can use.
     *
     * Takes the package NAME rather than hardcoding it, and that is what makes the not-installed
     * branch reachable from a test: with the name fixed, every branch below is decided by this
     * repository's own vendor tree, where WireKit is always present — so the guard clause would
     * be a line no run can enter, and a line no run can enter is one the 100% floor blocks the
     * release on. A caller passing a name that is genuinely absent exercises it honestly.
     *
     * The `class_exists` check that used to sit here is gone rather than hidden: this package is
     * installed by Composer, so Composer's own runtime class is present by construction. It was
     * a guard against a state that cannot occur, and it read as caution while being dead weight.
     */
    public static function packageSatisfiesWireKitFloor(string $package): bool
    {
        if (! InstalledVersions::isInstalled($package)) {
            return false;
        }

        return self::wireKitVersionSatisfies(InstalledVersions::getPrettyVersion($package));
    }

    /** Whether WireKit is installed at a version this package's WireKit tree can use. */
    public static function wireKitIsUsable(): bool
    {
        return self::packageSatisfiesWireKitFloor('pushery/wirekit');
    }

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/visual-feedback.php', 'visual-feedback');

        $this->app->singleton(Settings::class);
        // Singleton so the memoized measurement happens once per request: two
        // <x-visual-feedback::scripts /> tags on one page would otherwise hash the same
        // two files twice.
        $this->app->singleton(PublishedBundle::class);
        $this->app->singleton(CategoryLabels::class);
        $this->app->singleton(ContextRegistry::class);
        $this->app->bind(ResolvesReporter::class, GuardReporterResolver::class);

        // The abuse gate is the composite: the builtin floor ALWAYS runs, with additional
        // drivers (botgate) layered on top when available — so a provider outage
        // or a botgate-less install can never remove the floor.
        // The registry is a SINGLETON: a host registers its driver once, at boot, and every
        // resolution of the gate must see that registration.
        $this->app->singleton(AbuseGateRegistry::class);

        $this->app->bind(AbuseGate::class, fn (Application $app): AbuseGate => new AbuseGateManager(
            $app->make(BuiltinAbuseGate::class),
            // The configured driver's gate, or none — the floor above runs either way. This used
            // to be a hard-coded `[]`, which made `abuse.driver` a documented setting that
            // selected nothing.
            $app->make(AbuseGateRegistry::class)->additional(),
            $app->make(LoggerInterface::class),
        ));

        // The delivery-channel registry + its public manager (the VisualFeedback facade target).
        // The built-in channels register their own factories here; custom channels
        // register via VisualFeedback::extend(). Singletons so extend() persists for the request.
        $this->app->singleton(ChannelRegistry::class);
        $this->app->singleton(VisualFeedback::class);
        // Cache-backed per-report delivery receipts — present even for a mail-only, DB-less consumer.
        $this->app->singleton(ReceiptStore::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'visual-feedback');
        // A LIST, and the order is the whole mechanism: Laravel resolves a namespaced view
        // against these paths in turn, so putting the WireKit directory first makes every view
        // that exists in both trees resolve to the WireKit one, while everything that exists in
        // only the plain tree keeps working untouched.
        //
        // Done here rather than by choosing a view name in render(), and that is not a style
        // preference: the anonymous components <x-visual-feedback::fab> and ::trigger never go
        // through render(), so a name-based choice would serve a WireKit panel behind a plain
        // trigger. A path list covers every resolution in the package at once.
        //
        // A host's published copy in resources/views/vendor still wins over both — Laravel puts
        // it ahead of any package path — so publishing remains the way to actually EDIT the
        // templates, and this key is the way to CHOOSE between them without maintaining a copy.
        $this->loadViewsFrom(self::viewPaths(app(Settings::class)), 'visual-feedback');

        Livewire::component('visual-feedback.report-widget', ReportWidget::class);

        // The optional Matomo bridge — a no-op without the package (the listener's bridge guards it).
        Event::listen(ReportSubmitted::class, TrackReportSubmission::class);

        // Register the built-in delivery channels as lazy factories. Each is instantiated only
        // when its config key is enabled + available (ChannelRegistry), so an unused channel
        // costs nothing (a disabled channel's factory never runs; an enabled-but-unavailable
        // one — no mail recipient, no reports table, no webhook target — is dropped).
        $registry = $this->app->make(ChannelRegistry::class);
        $registry->register('mail', fn (): ReportChannel => $this->app->make(MailChannel::class));
        $registry->register('database', fn (): ReportChannel => $this->app->make(DatabaseChannel::class));
        $registry->register('webhook', fn (): ReportChannel => $this->app->make(WebhookChannel::class));

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->commands([PruneReports::class, ForgetReporter::class, SweepOrphanAttachments::class]);

            // `php artisan about` carries the state of the published bundle. Forgetting to
            // republish after an upgrade is the one failure this setup produces, and it was the
            // only one with no signal — the old copy keeps working and is simply the previous
            // release. The closure defers the measurement to the moment somebody asks, and the
            // console guard keeps it off every web request entirely.
            if (class_exists(AboutCommand::class)) {
                AboutCommand::add('Visual Feedback', fn (): array => [
                    'Published bundle' => $this->app->make(PublishedBundle::class)->label(),
                ]);
            }
        }
    }

    private function registerPublishing(): void
    {
        // Resolve publish targets through the Application contract's path methods
        // (available via illuminate/contracts), NOT the config_path()/lang_path()
        // global helpers. Those are Foundation helpers, shipped ONLY with
        // laravel/framework, so the helper form would fatal in a lean host. The
        // method form is behavior-identical. Each group also carries the bare
        // 'visual-feedback' umbrella tag, so `vendor:publish --tag="visual-feedback"`
        // publishes every resource at once — the convention the official skeleton sets.
        $this->publishes([
            __DIR__.'/../config/visual-feedback.php' => $this->app->configPath('visual-feedback.php'),
        ], ['visual-feedback', 'visual-feedback-config']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/visual-feedback'),
        ], ['visual-feedback', 'visual-feedback-lang']);

        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/visual-feedback'),
        ], ['visual-feedback', 'visual-feedback-views']);

        // The compiled JS bundle. Resolve the public path through the Application contract
        // (publicPath), NOT the public_path() Foundation helper, so it never fatals in a
        // lean host. Rides the umbrella tag — the assets are part of the normal install.
        $this->publishes([
            __DIR__.'/../dist' => $this->app->publicPath('vendor/visual-feedback'),
        ], ['visual-feedback', 'visual-feedback-assets']);

        // WireKit-native view variants. Publishing this tag OVERWRITES the plain stubs with
        // the token-based versions (built from real <x-wirekit::*> components), so it is
        // deliberately NOT part of the umbrella tag — publishing both at once contradicts
        // itself. The shared form partial keeps its wirekit/ path so the override's @include
        // still resolves to it.
        $this->publishes([
            __DIR__.'/../resources/views/wirekit/livewire/report-widget.blade.php' => $this->app->resourcePath('views/vendor/visual-feedback/livewire/report-widget.blade.php'),
            __DIR__.'/../resources/views/wirekit/livewire/_report-form.blade.php' => $this->app->resourcePath('views/vendor/visual-feedback/wirekit/livewire/_report-form.blade.php'),
            __DIR__.'/../resources/views/wirekit/components/fab.blade.php' => $this->app->resourcePath('views/vendor/visual-feedback/components/fab.blade.php'),
            __DIR__.'/../resources/views/wirekit/components/trigger.blade.php' => $this->app->resourcePath('views/vendor/visual-feedback/components/trigger.blade.php'),
        ], 'visual-feedback-wirekit');

        // The optional DatabaseChannel migration. DELIBERATELY not in the umbrella (like wirekit):
        // a standard install must NOT get the table — only a consumer who publishes this tag and
        // migrates opts in. Its 0001_… prefix keeps the file first, before the host's own migrations.
        $this->publishes([
            __DIR__.'/../database/migrations/optional/0001_01_01_000000_create_visual_feedback_reports_table.php' => $this->app->databasePath('migrations/0001_01_01_000000_create_visual_feedback_reports_table.php'),
        ], 'visual-feedback-migrations');
    }
}
