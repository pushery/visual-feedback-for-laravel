<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Attachments;

/**
 * Makes an untrusted client filename safe to store and to render as a mail attachment
 * name. Keeping `{uuid}_{clientName}` verbatim — the obvious approach — carries three attacks
 * straight into the admin's inbox:
 *
 *  - path components (`../../evil.png`) — traversal when the name is echoed into a path;
 *  - control / CR-LF characters — header/line injection in the mail client;
 *  - Unicode bidi overrides (RTL-override) — filename spoofing, so `photo\u{202E}gpj.exe`
 *    displays as `photoexe.jpg`.
 *
 * Every one is stripped here, the length is capped, and an empty result falls back to a
 * generated name. The returned value is BOTH the stored key's name part and the mail
 * attachment name, so the two can never disagree.
 *
 * The fourth attack is the banal one and it used to walk straight through: writing the
 * extension. Nothing above looks at it, so a file the reporter named `report.html` was stored
 * and mailed under that name — while every acceptance check in the package reads the BYTES
 * (Livewire's `mimes:` rule resolves the extension from the sniffed MIME, and the
 * AttachmentValidator runs finfo over the stored content). A PNG with HTML appended after IEND
 * is a valid PNG to all of them and an HTML document to the maintainer's browser the moment the
 * attachment is saved and opened. `$extension` closes that: the caller passes the extension it
 * derived from the content and whatever the client wrote is dropped.
 */
final class FilenameSanitizer
{
    /** Keep names short enough for every filesystem and mail client. */
    private const int MAX_LENGTH = 200;

    /**
     * @param  string  $fallback  used when the name sanitizes to nothing (e.g. a generated UUID)
     * @param  string|null  $extension  the extension derived from the file's CONTENT; it replaces
     *                                  whatever the client wrote. Null leaves the client's
     *                                  extension in place and is only correct where the name never
     *                                  becomes a filename — a caller that stores or mails the
     *                                  result passes the sniffed extension.
     */
    public function sanitize(string $name, string $fallback, ?string $extension = null): string
    {
        // Drop any directory part — only the final component is a filename.
        $name = basename(str_replace('\\', '/', $name));

        // Invalid UTF-8 first, so the class regexes below operate on a valid string.
        if (! mb_check_encoding($name, 'UTF-8')) {
            $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
        }

        // C0/C1 control characters (includes CR, LF, TAB, NUL) and DEL.
        $name = preg_replace('/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u', '', $name) ?? '';

        // Unicode bidi controls + zero-width marks used for filename spoofing.
        $name = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200B}-\x{200D}\x{FEFF}]/u', '', $name) ?? '';

        $name = trim($name);

        // A name that is empty or only dots carries no information — use the fallback.
        if ($name === '' || preg_match('/^\.+$/', $name) === 1) {
            $name = $fallback;
        }

        // Pinned BEFORE the cap, so the cap's extension-preserving branch works on the extension
        // that is actually going to be written. Capping first and replacing after could push the
        // result back over the limit whenever the truthful extension is the longer one.
        return $this->cap($this->pinExtension($name, $extension));
    }

    /**
     * Replace the name's extension with the content-derived one, or leave it when the caller has
     * none to give.
     *
     * The fallback goes through here too. It is a generated hash name, and Livewire builds that
     * one from `getClientOriginalExtension()` — the client's word again — so returning it
     * unpinned would hand back the very extension this parameter exists to discard.
     */
    private function pinExtension(string $name, ?string $extension): string
    {
        if ($extension === null || $extension === '') {
            return $name;
        }

        return pathinfo($name, PATHINFO_FILENAME).'.'.$extension;
    }

    /** Cap the length while preserving a short extension when there is one. */
    private function cap(string $name): string
    {
        if (mb_strlen($name) <= self::MAX_LENGTH) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);

        if ($extension !== '' && mb_strlen($extension) <= 10) {
            $keep = self::MAX_LENGTH - mb_strlen($extension) - 1;

            return mb_substr($name, 0, $keep).'.'.$extension;
        }

        return mb_substr($name, 0, self::MAX_LENGTH);
    }
}
