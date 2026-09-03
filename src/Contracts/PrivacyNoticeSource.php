<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Contracts;

/**
 * A host-provided source for the guest privacy notice. Set `privacy.source` to a
 * class name implementing this and the widget resolves it from the container to get
 * the notice URL a guest acknowledges. This is the `FQCN` privacy source — distinct
 * from `url` (a static configured URL) and `legal-consent` (the recording bridge, M7).
 */
interface PrivacyNoticeSource
{
    /** The notice URL a guest acknowledges, or null when this source has no notice. */
    public function url(): ?string;
}
