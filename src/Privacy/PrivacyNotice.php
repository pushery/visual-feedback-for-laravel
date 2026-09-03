<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Privacy;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Pushery\VisualFeedback\Contracts\PrivacyNoticeSource;
use Pushery\VisualFeedback\Contracts\PrivacyNoticeWordingSource;
use Pushery\VisualFeedback\Data\PrivacyNoticeWording;

/**
 * The privacy notice a GUEST must acknowledge before submitting. Distinct from consent:
 * this is only the "you can see we collect a screenshot + metadata" notice. Its source
 * is `config('visual-feedback.privacy.source')`:
 *
 *  - null            → no notice (nothing to acknowledge),
 *  - 'url'           → the configured `privacy.url`,
 *  - 'legal-consent' → the pushery/legal-consent bridge: the configured `privacy.url` PLUS the
 *                      published acknowledgment sentence as the label,
 *  - a FQCN          → a host PrivacyNoticeSource resolved from the container.
 *
 * A notice is required exactly when there is a URL, whatever the source. That is deliberate and
 * load-bearing: the URL is the full text the reporter can actually open, and a required checkbox
 * whose text cannot be opened is an uninformed clickwrap. So a wording source raises the QUALITY
 * of the label and never the question of whether a notice exists — which also keeps the views and
 * the submit-time check keyed on one and the same condition. They diverged once; a checkbox that
 * validation demands and the template never renders is unfixable from the reporter's side.
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
     * Only a source that CAN answer is asked — a plain URL source has no sentence to give and is
     * not expected to implement one.
     */
    public function wording(): ?PrivacyNoticeWording
    {
        $source = $this->source();

        return $source instanceof PrivacyNoticeWordingSource ? $source->wording() : null;
    }

    /** Whether a guest must acknowledge a notice before submitting. */
    public function required(): bool
    {
        return $this->url() !== null;
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
