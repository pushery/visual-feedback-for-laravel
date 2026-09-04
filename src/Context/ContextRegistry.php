<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Context;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use Pushery\VisualFeedback\Contracts\ReportContextProvider;
use Pushery\VisualFeedback\Data\ReportContextEntry;
use Throwable;

/**
 * Collects report context from the two registration paths and merges them:
 *
 *  1. instance entries — passed in from the widget's mount props, built by host code
 *     (wired to the widget through its mount props),
 *  2. global providers — the `config('visual-feedback.context_providers')` FQCNs,
 *     resolved from the container.
 *
 * Each provider AUTHORIZES its own entries — the host owns authorization — and the
 * whole context path is server-side: there is no client-callable action that sets
 * context.
 *
 * NOTHING A PROVIDER DOES MAY COST THE REPORT. Context is enrichment — it turns "it's
 * broken" into a diagnosable ticket — so losing it produces a worse ticket, never a lost one.
 * Losing the report is the expensive outcome, and every failure mode here is therefore logged
 * and stepped over: a class that does not implement the contract, a class that cannot be built
 * at all, and a provider that throws while collecting.
 *
 * That last pair used to be fatal, and the shape is worth remembering: this method is reached
 * from `submit()` and never from `render()`, so a host who renamed a class and left the old name
 * in `context_providers` saw a form that rendered perfectly and a 500 on every single
 * submission, with nothing logged as a configuration problem.
 */
final readonly class ContextRegistry
{
    public function __construct(
        private Container $container,
        private Repository $config,
        private LoggerInterface $logger,
    ) {}

    /**
     * Instance entries first, then the globally configured providers' entries.
     *
     * @param  list<ReportContextEntry>  $instanceEntries
     * @return list<ReportContextEntry>
     */
    public function collect(array $instanceEntries = []): array
    {
        return [...$instanceEntries, ...$this->fromProviders()];
    }

    /**
     * @return list<ReportContextEntry>
     */
    private function fromProviders(): array
    {
        $entries = [];

        foreach ($this->providerClasses() as $class) {
            // A CONTEXT PROVIDER MAY NOT COST THE REPORT. This used to call `make()` straight
            // out of the loop, so a host who renamed a class and left the old name in
            // `context_providers` got a `BindingResolutionException` out of `submit()` — a 500 on
            // `/livewire/update` on EVERY submission, with the form still rendering normally
            // (this method is reached from `submit()`, never from `render()`). Nothing was logged
            // as a configuration problem and no report was ever filed.
            //
            // Context is decoration on a report: it turns "it's broken" into a diagnosable
            // ticket, and losing it is a worse ticket, not a lost one. Losing the report is the
            // expensive outcome, so a provider that cannot be built is logged and stepped over.
            try {
                $provider = $this->container->make($class);
            } catch (Throwable $error) {
                $this->logger->warning(
                    'visual-feedback: a configured context provider could not be resolved and was skipped.',
                    ['provider' => $class, 'exception' => $error->getMessage()],
                );

                continue;
            }

            if (! $provider instanceof ReportContextProvider) {
                // Silent until now, and worth a line for the same reason: a class that exists but
                // does not implement the contract contributes nothing, and the host has no other
                // way to find out.
                $this->logger->warning(
                    'visual-feedback: a configured context provider does not implement ReportContextProvider and was skipped.',
                    ['provider' => $class],
                );

                continue;
            }

            try {
                $entries = [...$entries, ...$provider->entries()];
            } catch (Throwable $error) {
                // Same rule one step later: a provider that throws while collecting is the host's
                // code failing, and it must not take the reporter's message with it.
                $this->logger->warning(
                    'visual-feedback: a context provider threw while collecting entries and was skipped.',
                    ['provider' => $class, 'exception' => $error->getMessage()],
                );
            }
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function providerClasses(): array
    {
        $configured = $this->config->get('visual-feedback.context_providers', []);

        return array_values(array_filter(
            is_array($configured) ? $configured : [],
            is_string(...),
        ));
    }
}
