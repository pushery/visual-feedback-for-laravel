<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * The application locale as configured, read once when the package registers.
 *
 * `App::setLocale()` writes the locale it sets into `app.locale`, so the value read later in a
 * request is whatever a middleware, Livewire's locale restore or a previous job left there. The
 * report mail renders in the configured locale unless `mail.locale` names another one, so that
 * value is taken before anything can move it.
 */
final readonly class ConfiguredLocale
{
    public function __construct(public ?string $locale) {}

    public static function from(mixed $value): self
    {
        return new self(is_string($value) && $value !== '' ? $value : null);
    }
}
