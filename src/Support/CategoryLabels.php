<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Str;

/**
 * Resolves a category config key to its human label from lang. Labels live ONLY in
 * lang (`visual-feedback::messages.categories.<key>`), never in the config or a domain
 * class, so a mailable never reads categories back off a class, so the
 * label-to-domain circularity is structurally impossible.
 *
 * A category with no translation (a consumer's own `performance`, `accessibility`, …)
 * degrades to a humanized key — never a raw `visual-feedback::messages.categories.x`
 * token leaked into the UI.
 */
final readonly class CategoryLabels
{
    public function __construct(private Translator $translator) {}

    public function label(string $category): string
    {
        $key = "visual-feedback::messages.categories.{$category}";
        $label = $this->translator->get($key);

        return is_string($label) && $label !== $key ? $label : Str::headline($category);
    }
}
