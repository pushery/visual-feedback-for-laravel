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
     * Free-form reporter text rendered in a LIVE-Markdown position — a list item, a bold line.
     *
     * `cell()` is not enough here, and the gap is not cosmetic. It escapes pipes and folds
     * newlines, so a value cannot restructure a table or end its own row; it does nothing about
     * Markdown itself. Measured against the converter Laravel actually builds for a mail
     * (`allow_unsafe_links` off, CommonMark core plus tables): a reporter value of
     * `[click me](http://evil.example)` arrived in the rendered mail as a real `<a href>`, and
     * `![x](...)` as a real `<img src>`. That is the hole `fence()` exists for, one position
     * over, and its docblock already names the stake — a link in a maintainer's inbox that
     * appears to come from their own tooling.
     *
     * The escape set is deliberately NARROWER than "all ASCII punctuation", and the reason is
     * the other half of the mail. `renderText()` does not parse Markdown; it entity-decodes the
     * rendered body, so every backslash added here is VISIBLE in the text/plain alternative.
     * Escaping `.`, an apostrophe or `-` would put slashes through an ordinary name in a large
     * share of the mails a host ever sends. `<`, `>` and `&` are left out for the opposite
     * reason: Blade's echo and the converter's `html_input: escape` already render them inert.
     *
     * Both `preg_replace` results are cast to `string`. That is not style either: the function
     * returns null on an engine failure, and with `failOnDeprecation` on, a null flowing onward
     * is a red run at the first host that hits a backtrack limit.
     */
    public static function text(string $value): string
    {
        $folded = (string) preg_replace('/[\r\n]+/', ' ', $value);

        return (string) preg_replace('/[\\\\`*_\[\]()~|!]/', '\\\\$0', $folded);
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
