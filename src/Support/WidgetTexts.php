<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use InvalidArgumentException;

/**
 * The texts one widget may say in its own words instead of the catalog's.
 *
 * A contact form beside the feedback widget is the same component with other words: its own
 * heading, button, confirmation and failure line, `Topic` where the feedback widget says
 * `Category`, and its own sentence for a rate limit and a failed challenge. The host passes them as
 * the `texts` mount prop, so they can come from its own translations, and the widget seals them
 * with its other mount props: what the reporter reads is what the host wrote.
 *
 * A key the widget does not know, or a value that is not text, is a developer's mistake and fails
 * at the call site, as an invalid `recipient` does. A blank value counts as not given.
 */
final class WidgetTexts
{
    /** The keys a host may give, onto the catalog line each one replaces. */
    public const array KEYS = [
        'heading' => 'visual-feedback::messages.widget.heading',
        'submit' => 'visual-feedback::messages.widget.submit',
        'success' => 'visual-feedback::messages.widget.success',
        'error' => 'visual-feedback::messages.widget.error',
        'category' => 'visual-feedback::messages.widget.category_label',
        'rate_limited' => 'visual-feedback::messages.widget.rate_limited',
        'challenge_failed' => 'visual-feedback::messages.widget.challenge_failed',
    ];

    /**
     * The texts a mount received, held to the known keys and to text.
     *
     * @param  array<mixed>  $texts
     * @return array<string, string>
     */
    public static function of(array $texts): array
    {
        $kept = [];

        foreach ($texts as $key => $text) {
            if (! is_string($key) || ! array_key_exists($key, self::KEYS)) {
                throw new InvalidArgumentException(sprintf(
                    "visual-feedback: the texts mount prop knows %s, not '%s'.",
                    implode(', ', array_keys(self::KEYS)),
                    $key,
                ));
            }

            if (! is_string($text)) {
                throw new InvalidArgumentException("visual-feedback: the text for `{$key}` in the texts mount prop must be a string.");
            }

            if (trim($text) !== '') {
                $kept[$key] = $text;
            }
        }

        return $kept;
    }

    /**
     * A count the way a line of the catalog writes it, grouped by the thousands separator of the
     * catalog's language. An application changes the separator with the catalog line
     * `widget.help.thousands_separator`; the counter in the browser writes its count again in the
     * language of the page.
     */
    public static function count(int $value): string
    {
        return number_format($value, 0, '', (string) trans('visual-feedback::messages.widget.help.thousands_separator'));
    }

    /**
     * One text: the widget's own where it has one, otherwise the catalog's.
     *
     * @param  array<string, string>  $texts
     * @param  array<string, int|string>  $replace  the placeholders of the line, `:minutes` for a rate limit
     */
    public static function line(array $texts, string $key, array $replace = []): string
    {
        $own = $texts[$key] ?? null;

        if (is_string($own)) {
            $pairs = [];

            foreach ($replace as $placeholder => $value) {
                $pairs[':'.$placeholder] = (string) $value;
            }

            return strtr($own, $pairs);
        }

        $catalog = self::KEYS[$key] ?? self::KEYS['error'];

        return isset($replace['minutes']) && is_int($replace['minutes'])
            ? trans_choice($catalog, $replace['minutes'], $replace)
            : (string) trans($catalog, $replace);
    }
}
