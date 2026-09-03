<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Data;

/**
 * The acknowledgment sentence a guest was shown, plus what identifies the document it came from.
 *
 * All five fields or none: `text` is what the checkbox says, and `key`, `locale`, `version` and
 * `acceptanceFingerprint` are what makes a recorded acknowledgment resolvable later. Dropping any
 * of the four is not a simplification — a published document row may legitimately be deleted
 * (legal-consent calls that an ordinary admin/retention action and removed the foreign key on
 * purpose), and after that the record is only as good as what it carries itself. `version` alone
 * does not identify anything: two active rows `privacy/de` and `privacy/en` carry the same one.
 *
 * The four identifying fields are null together when the wording is this package's own lang line
 * rather than a published document — a notice source that has no document still has a sentence, and
 * a record must never suggest otherwise.
 *
 * `acceptanceFingerprint` is legal-consent's OWN value, computed by its hasher over the content
 * hash AND the wording. It is never recomputed here: the bare content hash covers the document
 * BODY while the guest reads the WORDING, and rebuilding the combined hash locally is the "second
 * hasher" legal-consent's own docblock calls not-a-guard.
 */
final readonly class PrivacyNoticeWording
{
    public function __construct(
        public string $text,
        public ?string $key = null,
        public ?string $locale = null,
        public ?string $version = null,
        public ?string $acceptanceFingerprint = null,
    ) {}

    /** This package's own sentence, carrying no document identity. */
    public static function builtIn(string $text): self
    {
        return new self($text);
    }

    /** Whether this wording identifies a published document. */
    public function isFromDocument(): bool
    {
        return $this->acceptanceFingerprint !== null;
    }

    /**
     * The provenance to store with a report, or an empty array for the built-in sentence.
     *
     * Keys are RESERVED: MetadataSanitizer strips this prefix unconditionally, so a client can
     * never supply one — not even when a consuming application adds the key to `metadata.collect`.
     *
     * @return array<string, string>
     */
    public function toMetadata(): array
    {
        if (! $this->isFromDocument()) {
            return [];
        }

        return array_filter([
            'privacy_notice_key' => $this->key,
            'privacy_notice_locale' => $this->locale,
            'privacy_notice_version' => $this->version,
            'privacy_notice_fingerprint' => $this->acceptanceFingerprint,
        ], is_string(...));
    }
}
