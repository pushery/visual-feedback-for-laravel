<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels\Mail;

/**
 * Renders one piece of user-influenced text safe for a Markdown mail body.
 * Blade's `{{ }}` already HTML-escapes, but Markdown has its own injection surface that HTML
 * escaping does not touch: an unescaped `|` inside a table cell shifts the columns and is the
 * `Bob | Alice` → "Undefined array key 1" crash, a newline ends the table row (or the list item)
 * early, and a bracket opens a link or an image. So a value is escaped and folded before Blade
 * escapes the rest — user text can never restructure the table or the list, or link anywhere.
 */
final class MailCell
{
    /**
     * A single Markdown table cell / list value: newlines folded to a space, and `|`, `[`, `]`
     * and the backslash escaped.
     *
     * The brackets are what a link and an image need: with every `[` escaped there is no link
     * opener, so `[pay here](https://…)` and `![](https://…/pixel.gif)` stay text. The backslash
     * is escaped so that one the browser sent cannot cancel the escape in front of a bracket.
     *
     * The set stops there on purpose. These cells carry the browser's metadata, and a user agent
     * always has parentheses and underscores. The text/plain alternative shows every escape as a
     * visible backslash, so `text()`'s wider set would mark up the user agent of every mail.
     */
    public static function cell(string $value): string
    {
        $folded = (string) preg_replace('/[\r\n]+/', ' ', $value);

        return (string) preg_replace('/[\\\\\[\]|]/', '\\\\$0', $folded);
    }

    /**
     * Free-form reporter text rendered in a live-Markdown position — a list item, a bold line.
     *
     * This is the wider set. `cell()` stops links and images and keeps a table intact; this
     * also escapes emphasis, code spans and the rest, so the reporter's own words cannot dress
     * themselves up as the mail's formatting either. Measured against the converter Laravel
     * actually builds for a mail (`allow_unsafe_links` off, CommonMark core plus tables): before
     * either escape existed, a reporter value of `[click me](http://evil.example)` arrived in the
     * rendered mail as a real `<a href>`, and `![x](...)` as a real `<img src>`. That is the hole
     * `fence()` exists for, one position over, and its docblock already names the stake — a link
     * in a maintainer's inbox that appears to come from their own tooling.
     *
     * The escape set is deliberately narrower than "all ASCII punctuation", and the reason is
     * the other half of the mail. `renderText()` does not parse Markdown; it entity-decodes the
     * rendered body, so every backslash added here is visible in the text/plain alternative.
     * Escaping `.`, an apostrophe or `-` would put slashes through an ordinary name in a large
     * share of the mails a host ever sends. `<`, `>` and `&` are left out for the opposite
     * reason: Blade's echo escapes them before the converter sees them. The converter itself
     * would not, because Laravel builds the mail converter with raw HTML allowed unless secured
     * encoding is switched on, so a value that reaches it without the echo has no such guard.
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
     * claimed it did. CommonMark closes a fence on the first line that begins with at least as
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
