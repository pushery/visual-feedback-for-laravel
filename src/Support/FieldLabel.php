<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * The label of a field the reporter may leave empty carries a note that says so: "(optional)" in
 * English, "(facultatif)" in French. Two places need the label without it. A field the
 * configuration makes required shows the star the form's legend explains instead, and a
 * validation message is already about one field, where restating that it is optional reads like
 * a mistake.
 *
 * The note is matched by its shape, a trailing parenthetical, so every locale is covered without
 * listing its word for "optional", and a label with brackets anywhere else keeps them.
 */
final class FieldLabel
{
    public static function withoutOptionalMarker(string $label): string
    {
        return trim((string) preg_replace('/\s*\([^()]*\)\s*$/u', '', $label));
    }
}
