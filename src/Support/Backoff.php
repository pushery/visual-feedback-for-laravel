<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * A channel's backoff as a queued job takes it: whole seconds between retries, or a list of them,
 * one per retry, which the queue repeats from its last entry.
 *
 * `channels.<name>.backoff` used to take one number, and a host that waited longer after each
 * failed send, `[30, 120, 300]`, had to give that up. A value that is neither a number of seconds
 * nor a list of them falls back to 30 seconds, the shipped default, rather than to no wait at all:
 * a typo in a retry setting must not turn every failure into an immediate second attempt.
 */
final class Backoff
{
    public const int DEFAULT = 30;

    /**
     * @return int|non-empty-list<int>
     */
    public static function from(mixed $configured): int|array
    {
        if (is_numeric($configured)) {
            return (int) $configured >= 0 ? (int) $configured : self::DEFAULT;
        }

        if (! is_array($configured) || $configured === [] || ! array_is_list($configured)) {
            return self::DEFAULT;
        }

        $seconds = [];

        foreach ($configured as $value) {
            if (! is_numeric($value) || (int) $value < 0) {
                return self::DEFAULT;
            }

            $seconds[] = (int) $value;
        }

        return $seconds;
    }
}
