<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * What turns the plain tree's colors dark: the operating system, or a selector of the application.
 *
 * `ui.dark_mode` is `media` by default, and the colors follow `prefers-color-scheme`. An application
 * with a dark mode of its own usually switches it with a class on `<html>` instead, the one
 * Tailwind's `dark` variant and WireKit's theme script set. A reader who picked dark in such an
 * application on a light system then saw a light widget in a dark page, and the reverse. `class`
 * follows `ui.dark_selector` instead, `.dark` unless the host names another.
 *
 * The selector is written into a stylesheet as it stands, so it is held to what cannot leave its
 * rule: letters, digits, spaces and the punctuation of selectors, with every quote, bracket and
 * parenthesis closed in order, and without a brace, a semicolon, a backslash, a slash or an opening
 * angle bracket. Any other value is `.dark`, the one a host most likely meant. A typo in a theme
 * setting must not leave the widget light for good, nor take the rules after it down with it.
 */
final class DarkScheme
{
    public const string DEFAULT_SELECTOR = '.dark';

    /** The longest selector accepted, well above any a theme switch needs. */
    private const int MAX_LENGTH = 200;

    /**
     * The selector the dark colors stand under, or null where they follow the operating system.
     */
    public static function selector(mixed $mode, mixed $selector): ?string
    {
        if ($mode !== 'class') {
            return null;
        }

        $selector = is_string($selector) ? trim($selector) : '';

        return self::isContained($selector) ? $selector : self::DEFAULT_SELECTOR;
    }

    /**
     * Whether a selector is one that cannot end its own rule or open another.
     */
    private static function isContained(string $selector): bool
    {
        if ($selector === '' || strlen($selector) > self::MAX_LENGTH
            || preg_match('/^[A-Za-z0-9_.#:,=*+~>()\[\]"\' -]+$/', $selector) !== 1) {
            return false;
        }

        $open = [];
        $quote = null;

        foreach (str_split($selector) as $character) {
            if ($quote !== null) {
                $quote = $character === $quote ? null : $quote;

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '[' || $character === '(') {
                $open[] = $character;
            } elseif ($character === ']' || $character === ')') {
                if (array_pop($open) !== ($character === ']' ? '[' : '(')) {
                    return false;
                }
            }
        }

        return $quote === null && $open === [];
    }
}
