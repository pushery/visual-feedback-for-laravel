<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback;

use Illuminate\Contracts\Foundation\Application;
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
use Pushery\VisualFeedback\Support\Settings;

final class VisualFeedbackServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/visual-feedback.php', 'visual-feedback');

        $this->app->singleton(Settings::class);
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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'visual-feedback');

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
