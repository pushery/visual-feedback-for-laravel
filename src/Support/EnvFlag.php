<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * A boolean switch read out of the configuration, where the value may still be a raw `env()`
 * string.
 */
final class EnvFlag
{
    /**
     * A protective switch, where an empty value means "not set".
     *
     * `env()` hands over a set but empty variable as `''`, and FILTER_VALIDATE_BOOLEAN reads `''`
     * as false. For a switch whose default protects, `VISUAL_FEEDBACK_EXAMPLE=` would then turn the
     * protection off. Here a blank value takes the default, and only an explicit false switches it
     * off.
     */
    public static function protection(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Any other switch, read the way the shipped config parses it.
     *
     * A config published before 0.7.0 still holds `env('…', true)` for its switches, and the
     * recursive merge fills only keys that are missing, so such a file hands over `'off'` as a
     * string, which a `(bool)` cast reads as on. Here `off`, `no`, `false`, `0` and a blank value
     * read as false and `on`, `yes`, `true` and `1` as true, as FILTER_VALIDATE_BOOLEAN reads them
     * in the shipped file. A missing key and an unreadable value take the default.
     */
    public static function boolean(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
