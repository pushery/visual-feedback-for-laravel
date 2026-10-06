<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Attachments\AttachmentPolicy;
use Pushery\VisualFeedback\Channels\Mail\TransportDeliverability;
use Pushery\VisualFeedback\Contracts\ReportChannel;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Jobs\SendReportMail;
use Pushery\VisualFeedback\Support\ConfiguredLocale;
use Pushery\VisualFeedback\Support\EnvFlag;

/**
 * The mail delivery channel: it records a pending receipt and enqueues the channel's own
 * SendReportMail job, tuned per `channels.mail` (queue / tries / backoff) and rendered in the
 * configured mail locale — never the random worker locale. It is available when a recipient is
 * configured and the configured mailer can actually put a message on the wire. The terminal
 * delivered/failed receipt, the lifecycle events and the attachment refcount flow through the
 * ReportDeliveryTracker from inside the job; here the receipt is marked pending.
 */
final readonly class MailChannel implements ReportChannel
{
    public function __construct(
        private Config $config,
        private Bus $bus,
        private ReceiptStore $receipts,
        private Translator $translator,
        private Application $app,
        private LoggerInterface $logger,
        private TransportDeliverability $transports,
        private ConfiguredLocale $configuredLocale,
    ) {}

    public function key(): string
    {
        return 'mail';
    }

    /**
     * Available with a configured recipient and a transport that delivers.
     *
     * The second half matters as much as the first. With `mail.to` set and `MAIL_MAILER` not, the
     * message goes into `laravel.log` and every seam downstream reports success, delivered receipt
     * included. A mailer that accepts and drops has exactly as much "where to" as an empty
     * `mail.to`.
     */
    public function isAvailable(): bool
    {
        $to = $this->config->get('visual-feedback.mail.to');

        if (! is_string($to) || trim($to) === '') {
            return false;
        }

        return $this->transportDelivers();
    }

    /**
     * Never asked while the application is running its tests, and that carve-out is what makes
     * the check shippable rather than a nicety.
     *
     * Under a test harness the answer is `array` by construction — Testbench sets it, and so does
     * nearly every consumer's `phpunit.xml`. A guard that refused there would switch this channel
     * off inside every consuming application's suite, so `Mail::assertQueued()` and
     * `Queue::assertPushed(SendReportMail::class)` would stop passing in code that has nothing
     * wrong with it. Breaking every consumer's tests to report a production defect is a worse
     * trade than the defect.
     *
     * The environment is read off the application, not off `config('app.env')`. Under Testbench
     * those two disagree — the container says `testing` and the config says `local`, because
     * Testbench sets `$app['env']` directly — and the config would answer the wrong question with
     * a straight face.
     *
     * `mail.require_deliverable_transport` turns it off for the deliberate case: a developer on
     * `MAIL_MAILER=log` who wants to read the rendered report in the log file rather than have the
     * channel refuse. Off by choice is a different thing from off by accident, which is the whole
     * distinction this guard exists to restore.
     */
    private function transportDelivers(): bool
    {
        if ($this->app->environment('testing')) {
            return true;
        }

        $required = $this->config->get('visual-feedback.mail.require_deliverable_transport', true);

        if (filter_var($required, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false) {
            return true;
        }

        $dropping = $this->transports->nonDeliveringTransports();

        if ($dropping === []) {
            return true;
        }

        // Loud, because the alternative is a configuration that looks honored and is not — the
        // same reason LegalConsentNotice and AbuseGateRegistry are loud about their own refusals.
        $this->logger->warning('visual-feedback: the configured mailer cannot deliver — its transport accepts a message and drops it, so the mail channel is skipped instead of reporting a delivery that never happened', [
            'mailer' => $this->config->get('mail.default'),
            'transports' => $dropping,
            'hint' => 'set MAIL_MAILER to a real transport, or set visual-feedback.mail.require_deliverable_transport to false if this is deliberate',
        ]);

        return false;
    }

    public function dispatch(Report $report): void
    {
        $this->receipts->recordPending($report->id, $this->key());

        $job = new SendReportMail(
            report: $report,
            mail: $this->mailConfig($report),
            locale: $this->renderLocale($report),
            tries: $this->tries(),
            backoffSeconds: $this->backoff(),
        );

        // Connection before queue, matching the order a host reads them in the config file.
        // This is a queue connection, so it decides which worker carries the job — a host
        // that leaves it unset keeps the application default, which is the common case.
        $connection = $this->config->get('visual-feedback.channels.mail.connection');

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        $queue = $this->config->get('visual-feedback.channels.mail.queue');

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        $this->bus->dispatch($job);
    }

    private function tries(): int
    {
        $tries = $this->config->get('visual-feedback.channels.mail.tries');

        return is_numeric($tries) && (int) $tries > 0 ? (int) $tries : 3;
    }

    private function backoff(): int
    {
        $backoff = $this->config->get('visual-feedback.channels.mail.backoff');

        return is_numeric($backoff) && (int) $backoff >= 0 ? (int) $backoff : 30;
    }

    /**
     * @return array{to: ?string, from: array{address: ?string, name: ?string}, reply_to_reporter: bool, attach_files: bool, disk: ?string, subject_excerpt_length: int}
     */
    private function mailConfig(Report $report): array
    {
        $mail = $this->config->get('visual-feedback.mail');
        $mail = is_array($mail) ? $mail : [];
        $from = isset($mail['from']) && is_array($mail['from']) ? $mail['from'] : [];
        $disk = new AttachmentPolicy($this->config)->disk();
        $excerpt = $mail['subject_excerpt_length'] ?? null;

        return [
            // A per-report recipient overrides `mail.to` — the widget can be mounted with
            // one on a page whose feedback belongs to a different team.
            //
            // The value is not locked -- a lock throws when a request writes it and answers with a
            // 419, which reached ordinary navigation -- so it can be written into the snapshot,
            // and it still cannot move a report: the widget permits only `mail.to` or an address
            // the host declared under `mail.allowed_recipients`, and answers null for anything
            // else, which is this line's fallback. The address is also checked at the boundary,
            // so a header-injecting newline never reaches here.
            'to' => $report->recipient ?? (is_string($mail['to'] ?? null) ? $mail['to'] : null),
            'from' => [
                'address' => is_string($from['address'] ?? null) ? $from['address'] : null,
                'name' => is_string($from['name'] ?? null) ? $from['name'] : null,
            ],
            'reply_to_reporter' => EnvFlag::boolean($mail['reply_to_reporter'] ?? null, false),
            'attach_files' => EnvFlag::boolean($mail['attach_files'] ?? null, true),
            'disk' => $disk,
            // Read here rather than in the mailable, because this class is the one place the
            // mail configuration is turned into a value the queue can carry. A mailable that
            // reached for config() itself would resolve it in the worker, whose configuration is
            // not necessarily the one the report was accepted under.
            'subject_excerpt_length' => is_numeric($excerpt) ? max(0, (int) $excerpt) : 60,
        ];
    }

    /**
     * The render locale: `mail.locale` = a concrete locale, `reporter` (the reporter's language,
     * folded from the metadata), or empty → the app's configured locale. Never the worker's.
     *
     * The app locale is resolved here and carried, because a job without a locale renders in
     * whatever locale is active where it runs: on the `sync` connection the one Livewire restored
     * for the reporter's page, in a worker the one the previous job left behind.
     */
    private function renderLocale(Report $report): ?string
    {
        $configured = $this->config->get('visual-feedback.mail.locale');

        if ($configured === 'reporter') {
            $language = $report->metadata['language'] ?? null;
            $folded = is_string($language) && $language !== '' ? $this->foldToATranslatableLocale($language) : null;

            return $folded ?? $this->appLocale();
        }

        return is_string($configured) && $configured !== '' ? $configured : $this->appLocale();
    }

    /** The application's configured locale, as it stood when the package registered. */
    private function appLocale(): ?string
    {
        return $this->configuredLocale->locale;
    }

    /**
     * Fold a browser language tag onto a locale that can actually be rendered.
     *
     * The tag comes from `navigator.language`, which is BCP-47 and carries a region for most
     * reporters — `de-DE`, `pt-BR`, `en-US`. Laravel does not strip a region: it looks for
     * `de-DE`, does not find it, and falls straight to `fallback_locale`, so
     * `trans($key, [], 'de')` is "Nachricht" while `trans($key, [], 'de-DE')` is "Message".
     * Passed through raw, the tag would render `mail.locale=reporter` in the fallback language for
     * every reporter whose browser sends a region, which is nearly all of them.
     *
     * The exact tag is tried first and that ordering is load-bearing rather than tidy: `pt-BR`
     * and `pt-PT` are different translations, so a host that publishes `pt-BR` must win over the
     * base. Only when nothing renders in the full tag is the region dropped.
     *
     * Whether a locale renders is asked of the translator with `$fallback: false` — with the
     * fallback on, every locale on earth answers yes and the probe measures nothing. The key is
     * one this package ships in all seven of its locales, so the question is really "can this
     * mail be rendered", not "does the host happen to translate something".
     *
     * A tag that folds to nothing returns null, and the caller answers with the app's own locale.
     * That is better than forcing an untranslatable tag: the result would be the fallback either
     * way, and this lets a host that set `app.locale` deliberately keep it.
     *
     * The tag is checked for the shape of a language tag before the first lookup. It is the
     * browser's value, the translator turns a locale into a directory and a file name and
     * `require`s what it finds there, and only `Translator::setLocale()` refuses the characters
     * that would leave the lang directory, a call this lookup never makes.
     */
    private function foldToATranslatableLocale(string $tag): ?string
    {
        if (preg_match('/\A[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{1,8}){0,3}\z/', $tag) !== 1) {
            return null;
        }

        $probe = 'visual-feedback::messages.mail.message';

        $candidates = [$tag];

        // BCP-47 separates subtags with a hyphen; Laravel's own directory convention uses an
        // underscore, and a host may have published either. Both are folded from the same tag.
        foreach (['-', '_'] as $separator) {
            if (str_contains($tag, $separator)) {
                $candidates[] = strstr($tag, $separator, true);
            }
        }

        foreach (array_unique(array_filter($candidates)) as $candidate) {
            if ($this->translator->has($probe, $candidate, false)) {
                return $candidate;
            }
        }

        return null;
    }
}
