<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Privacy;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Htmlable;
use Pushery\VisualFeedback\Contracts\PrivacyNoticeContentSource;
use Pushery\VisualFeedback\Contracts\PrivacyNoticeSource;
use Pushery\VisualFeedback\Contracts\PrivacyNoticeWordingSource;
use Pushery\VisualFeedback\Data\PrivacyNoticeWording;

/**
 * The privacy notice a guest must acknowledge before submitting. Distinct from consent:
 * this is only the "you can see we collect a screenshot + metadata" notice. Its source
 * is `config('visual-feedback.privacy.source')`:
 *
 *  - null            → no notice (nothing to acknowledge),
 *  - 'url'           → the configured `privacy.url`,
 *  - 'legal-consent' → the pushery/legal-consent bridge: the configured `privacy.url` plus the
 *                      published acknowledgment sentence as the label,
 *  - a FQCN          → a host PrivacyNoticeSource resolved from the container.
 *
 * A notice is required exactly when there is something the reporter can read: a URL to open, or
 * the text a PrivacyNoticeContentSource hands the form. That is deliberate and load-bearing: a
 * required checkbox whose text cannot be read is an uninformed clickwrap. So a wording source raises
 * the quality of the label and never the question of whether a notice exists, which also keeps the
 * views and the submit-time check keyed on one and the same condition. They diverged once; a
 * checkbox that validation demands and the template never renders is unfixable from the reporter's
 * side.
 *
 * A guest is asked wherever a notice is there. A signed-in reporter is asked where `privacy.ask` is
 * `everyone`, for every widget or for one under `privacy.instances`, as a contact form asks everybody
 * who writes to it.
 */
final readonly class PrivacyNotice
{
    public function __construct(
        private Repository $config,
        private Container $container,
    ) {}

    /** The notice URL, or null when no acknowledgeable notice is configured. */
    public function url(): ?string
    {
        $source = $this->config->get('visual-feedback.privacy.source');

        if ($source === 'url') {
            $url = $this->config->get('visual-feedback.privacy.url');

            return is_string($url) && $url !== '' ? $url : null;
        }

        return $this->source()?->url();
    }

    /**
     * The sentence to put on the checkbox, or null to use this package's own lang line.
     *
     * Only a source that can answer is asked — a plain URL source has no sentence to give and is
     * not expected to implement one.
     */
    public function wording(): ?PrivacyNoticeWording
    {
        $source = $this->source();

        return $source instanceof PrivacyNoticeWordingSource ? $source->wording() : null;
    }

    /**
     * The text of the notice for a widget, which the form shows beside the checkbox, or null where the
     * source supplies none. `$instance` is the widget's name, null for one without a name and for the
     * standalone page.
     */
    public function content(?string $instance = null): ?Htmlable
    {
        $source = $this->source();

        return $source instanceof PrivacyNoticeContentSource ? $source->content($instance) : null;
    }

    /** Whether a notice is there to acknowledge: a page to open, or a text the form shows. */
    public function required(?string $instance = null): bool
    {
        return $this->url() !== null || $this->content($instance) instanceof Htmlable;
    }

    /**
     * Whether this reporter acknowledges the notice in this widget before submitting: a guest wherever
     * a notice is there, a signed-in reporter where the widget asks everyone.
     */
    public function askedOf(bool $isGuest, ?string $instance = null): bool
    {
        return ($isGuest || $this->asksEveryone($instance)) && $this->required($instance);
    }

    /**
     * Whether the widget asks signed-in reporters too: `privacy.instances.<name>.ask` for a widget of
     * that name, otherwise `privacy.ask`. Only `everyone` asks them, so a typo keeps the guests-only
     * default rather than turning the form against every signed-in reporter.
     */
    private function asksEveryone(?string $instance): bool
    {
        $instances = $this->config->get('visual-feedback.privacy.instances');
        $own = $instance !== null && is_array($instances) && is_array($instances[$instance] ?? null) ? ($instances[$instance]['ask'] ?? null) : null;

        return (is_string($own) ? $own : $this->config->get('visual-feedback.privacy.ask')) === 'everyone';
    }

    /**
     * The configured source object, or null when the configuration names none.
     *
     * `legal-consent` is a stable config sentinel rather than a class name, so hosts never have to
     * write an internal FQCN into their config and the bridge can move.
     */
    private function source(): ?PrivacyNoticeSource
    {
        $source = $this->config->get('visual-feedback.privacy.source');

        if ($source === 'legal-consent') {
            $source = LegalConsentNotice::class;
        }

        if (! is_string($source) || ! is_a($source, PrivacyNoticeSource::class, true)) {
            return null;
        }

        $instance = $this->container->make($source);

        return $instance instanceof PrivacyNoticeSource ? $instance : null;
    }
}
