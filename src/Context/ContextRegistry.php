<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Context;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Pushery\VisualFeedback\Contracts\ReportContextProvider;
use Pushery\VisualFeedback\Data\ReportContextEntry;

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
 * context. A configured class that does not implement the contract is skipped rather
 * than fataling every submission (context is enrichment, not a security gate).
 */
final readonly class ContextRegistry
{
    public function __construct(
        private Container $container,
        private Repository $config,
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
            $provider = $this->container->make($class);

            if ($provider instanceof ReportContextProvider) {
                $entries = [...$entries, ...$provider->entries()];
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
