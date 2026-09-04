<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Translation\Translator;
use Pushery\VisualFeedback\Contracts\ReportChannel;
use Pushery\VisualFeedback\Data\Report;
use Pushery\VisualFeedback\Jobs\SendReportMail;

/**
 * The mail delivery channel: it records a pending receipt and enqueues the channel's own
 * SendReportMail job, tuned per `channels.mail` (queue / tries / backoff) and rendered in the
 * configured mail locale — never the random worker locale. It is only available when a
 * recipient is configured (no `mail.to` → nothing to deliver to). The terminal delivered/failed
 * receipt, the lifecycle events and the attachment refcount flow through the ReportDeliveryTracker
 * from inside the job; here the receipt is marked pending.
 */
final readonly class MailChannel implements ReportChannel
{
    public function __construct(
        private Config $config,
        private Bus $bus,
        private ReceiptStore $receipts,
        private Translator $translator,
    ) {}

    public function key(): string
    {
        return 'mail';
    }

    /** Available only with a configured recipient — a channel with nowhere to send is skipped. */
    public function isAvailable(): bool
    {
        $to = $this->config->get('visual-feedback.mail.to');

        return is_string($to) && trim($to) !== '';
    }

    public function dispatch(Report $report): void
    {
        $this->receipts->record($report->id, $this->key(), DeliveryStatus::Pending);

        $job = new SendReportMail(
            report: $report,
            mail: $this->mailConfig($report),
            locale: $this->renderLocale($report),
            tries: $this->tries(),
            backoffSeconds: $this->backoff(),
        );

        // Connection before queue, matching the order a host reads them in the config file.
        // This is a QUEUE connection, so it decides which worker carries the job — a host
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
     * @return array{to: ?string, from: array{address: ?string, name: ?string}, reply_to_reporter: bool, attach_files: bool, disk: ?string}
     */
    private function mailConfig(Report $report): array
    {
        $mail = $this->config->get('visual-feedback.mail');
        $mail = is_array($mail) ? $mail : [];
        $from = isset($mail['from']) && is_array($mail['from']) ? $mail['from'] : [];
        $disk = $this->config->get('visual-feedback.attachments.disk');

        return [
            // A per-report recipient overrides `mail.to` — the widget can be mounted with
            // one on a page whose feedback belongs to a different team. It is a #[Locked]
            // mount prop validated as an address at the boundary, so it can neither be set
            // from the browser nor carry a header-injecting newline this far.
            'to' => $report->recipient ?? (is_string($mail['to'] ?? null) ? $mail['to'] : null),
            'from' => [
                'address' => is_string($from['address'] ?? null) ? $from['address'] : null,
                'name' => is_string($from['name'] ?? null) ? $from['name'] : null,
            ],
            'reply_to_reporter' => (bool) ($mail['reply_to_reporter'] ?? false),
            'attach_files' => (bool) ($mail['attach_files'] ?? true),
            'disk' => is_string($disk) && $disk !== '' ? $disk : null,
        ];
    }

    /**
     * The render locale: `mail.locale` = a concrete locale, `reporter` (the reporter's language,
     * folded from the metadata), or null → the app's configured locale. Never the worker's.
     */
    private function renderLocale(Report $report): ?string
    {
        $configured = $this->config->get('visual-feedback.mail.locale');

        if ($configured === 'reporter') {
            $language = $report->metadata['language'] ?? null;

            return is_string($language) && $language !== '' ? $this->foldToATranslatableLocale($language) : null;
        }

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    /**
     * Fold a browser language tag onto a locale that can actually be rendered.
     *
     * THE DOCBLOCK ABOVE SAID "folded from the metadata" WHILE THE VALUE WAS PASSED THROUGH
     * RAW, AND THAT COST THE FEATURE ITS ORDINARY CASE. The tag comes from `navigator.language`,
     * which is BCP-47 and carries a region for most reporters — `de-DE`, `pt-BR`, `en-US`. Laravel
     * does not strip a region: it looks for `de-DE`, does not find it, and falls straight to
     * `fallback_locale`. Measured on this tree: `trans($key, [], 'de')` is "Nachricht" and
     * `trans($key, [], 'de-DE')` is "Message". So `mail.locale=reporter` rendered ENGLISH for
     * every reporter whose browser sends a region — which is nearly all of them — while the one
     * test covering the feature passed a bare `pt` and stayed green.
     *
     * The exact tag is tried FIRST and that ordering is load-bearing rather than tidy: `pt-BR`
     * and `pt-PT` are different translations, so a host that publishes `pt-BR` must win over the
     * base. Only when nothing renders in the full tag is the region dropped.
     *
     * Whether a locale renders is asked of the translator with `$fallback: false` — with the
     * fallback on, every locale on earth answers yes and the probe measures nothing. The key is
     * one this package ships in all seven of its locales, so the question is really "can this
     * mail be rendered", not "does the host happen to translate something".
     *
     * A tag that folds to nothing returns null, which means the app's own locale. That is better
     * than forcing an untranslatable tag: the result would be the fallback either way, and null
     * at least lets a host that set `app.locale` deliberately keep it.
     */
    private function foldToATranslatableLocale(string $tag): ?string
    {
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
