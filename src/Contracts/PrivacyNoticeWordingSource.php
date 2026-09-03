<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Contracts;

use Pushery\VisualFeedback\Data\PrivacyNoticeWording;

/**
 * A notice source that supplies the acknowledgement SENTENCE, not only a link to a page.
 *
 * Kept separate from PrivacyNoticeSource rather than added to it, because supplying a wording is
 * a capability, not a duty: a host that has nothing but a privacy URL implements the base
 * contract and is done, and does not have to write `return null;` for a method it has no answer
 * for. PrivacyNotice checks `instanceof` and asks only a source that can answer.
 *
 * The reason this exists at all: `url()` alone cannot bridge to pushery/legal-consent, which has
 * no notice URL to give — that is deliberate on its side, not a gap ("The PUBLISHED document a
 * public page must render": the consuming app owns the page, its layout and its routing). So the
 * one thing a bridge can carry across is the text.
 *
 * It returns the sentence AND what identifies the document it came from. That second half was
 * impossible until legal-consent v0.7.0: the acceptance fingerprint is computed over the content
 * hash AND the wording, and its helper took a model the documented read path never hands out.
 * Recording the bare content hash instead would have hashed the document BODY while the guest read
 * the WORDING — a proof over the wrong text, which is worse than none. That release put the
 * fingerprint on the DTO, so the identity travels with the sentence.
 */
interface PrivacyNoticeWordingSource extends PrivacyNoticeSource
{
    /** The sentence to put on the checkbox, or null to use the package's own wording. */
    public function wording(): ?PrivacyNoticeWording;
}
