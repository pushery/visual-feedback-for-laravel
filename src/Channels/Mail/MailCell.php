<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels\Mail;

/**
 * Renders one piece of user-influenced text safe for a Markdown MAIL body.
 * Blade's `{{ }}` already HTML-escapes, but Markdown has its own injection surface that HTML
 * escaping does not touch: an unescaped `|` inside a table cell shifts the columns and is the
 * `Bob | Alice` → "Undefined array key 1" crash, and a newline ends the table row (or
 * the list item) early. So a cell value gets its pipes Markdown-escaped and its CR/LF folded to
 * a space BEFORE Blade escapes the rest — user text can never restructure the table or the list.
 */
final class MailCell
{
    /** A single Markdown table cell / list value: pipes escaped, newlines folded to a space. */
    public static function cell(string $value): string
    {
        $folded = (string) preg_replace('/[\r\n]+/', ' ', $value);

        return str_replace('|', '\|', $folded);
    }

    /**
     * The opening/closing delimiter for a fenced code block that $value cannot break out of.
     *
     * A hardcoded ``` fence does not make user text inert, and the mail template's own comment
     * claimed it did. CommonMark closes a fence on the first line that begins with AT LEAST as
     * many backticks as opened it, so a reporter who types three backticks on a line of their own
     * ends the block and everything after it renders as live Markdown. Measured: a message
     * carrying a fence and `[CLICK ME](https://evil.example)` produced
     * `<a href="https://evil.example">` in the rendered mail. HTML stays inert either way —
     * Blade's `{{ }}` handles that — but links, images, tables and headings do not, and a link
     * in a maintainer's inbox that appears to come from their own tooling is the whole game.
     *
     * So the fence is longer than the longest run of backticks in the content: N+1, minimum
     * three. That is the CommonMark-sanctioned way to quote arbitrary text, and it changes not a
     * single character of what the reporter wrote — an escaping approach would.
     */
    public static function fence(string $value): string
    {
        preg_match_all('/`+/', $value, $runs);

        $longest = 0;

        foreach ($runs[0] as $run) {
            $longest = max($longest, strlen($run));
        }

        return str_repeat('`', max(3, $longest + 1));
    }

    /** A metadata scalar rendered as a safe cell — booleans and null get a stable display form. */
    public static function value(int|float|string|bool|null $value): string
    {
        $string = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => '—',
            default => (string) $value,
        };

        return self::cell($string);
    }
}
