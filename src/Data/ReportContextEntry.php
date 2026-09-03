<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Data;

/**
 * One structured context datum attached to a report (app version, tenant, the profile
 * or record a report is about, …). Every field is a plain string, so an entry is always
 * queue-safe and renders identically in every channel. `label` is the human-readable
 * heading, `value` the stringified datum. `url` is a ready-built link the HOST supplies
 * (no route-name coupling — the host builds its own URLs), and `identifier` an optional
 * stable id for the referenced record.
 */
final readonly class ReportContextEntry
{
    public function __construct(
        public string $key,
        public string $label,
        public string $value,
        public ?string $url = null,
        public ?string $identifier = null,
    ) {}

    /**
     * @return array{key: string, label: string, value: string, url: ?string, identifier: ?string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'url' => $this->url,
            'identifier' => $this->identifier,
        ];
    }
}
