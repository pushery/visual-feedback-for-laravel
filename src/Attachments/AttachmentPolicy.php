<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Attachments;

use Illuminate\Contracts\Config\Repository;

/**
 * The single source of truth for what an attachment may be. A hardcoded
 * `accept="image/*,…"` drifts against a separately maintained server MIME list; here the HTML `accept`
 * attribute, the Livewire perimeter rules, and the AttachmentValidator's allowlist are ALL
 * derived from the one `attachments.mimes` config via this policy, so they can never
 * disagree.
 */
final readonly class AttachmentPolicy
{
    /** Config short form → its file extension and canonical MIME type. */
    private const array MAP = [
        'jpeg' => ['ext' => 'jpeg', 'mime' => 'image/jpeg'],
        'jpg' => ['ext' => 'jpg', 'mime' => 'image/jpeg'],
        'png' => ['ext' => 'png', 'mime' => 'image/png'],
        'gif' => ['ext' => 'gif', 'mime' => 'image/gif'],
        'webp' => ['ext' => 'webp', 'mime' => 'image/webp'],
        'heic' => ['ext' => 'heic', 'mime' => 'image/heic'],
        'heif' => ['ext' => 'heif', 'mime' => 'image/heif'],
        'mp4' => ['ext' => 'mp4', 'mime' => 'video/mp4'],
        'quicktime' => ['ext' => 'mov', 'mime' => 'video/quicktime'],
    ];

    public function __construct(private Repository $config) {}

    /**
     * The configured short forms, filtered to the ones this policy knows.
     *
     * @return list<string>
     */
    public function shortForms(): array
    {
        $configured = $this->config->get('visual-feedback.attachments.mimes');

        return array_values(array_filter(
            is_array($configured) ? $configured : [],
            fn (mixed $short): bool => is_string($short) && isset(self::MAP[$short]),
        ));
    }

    /**
     * The canonical MIME types the server accepts — the AttachmentValidator's allowlist.
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        $types = array_map(fn (string $short): string => self::MAP[$short]['mime'], $this->shortForms());

        return array_values(array_unique($types));
    }

    /**
     * The extensions for Laravel's `mimes:` validation rule (the perimeter), e.g. "jpeg,png,mov".
     */
    public function ruleExtensions(): string
    {
        $extensions = array_map(fn (string $short): string => self::MAP[$short]['ext'], $this->shortForms());

        return implode(',', array_values(array_unique($extensions)));
    }

    /**
     * The HTML `accept` attribute — the SAME allowlist, as MIME types plus dotted
     * extensions so both desktop and mobile file pickers filter correctly.
     */
    public function acceptAttribute(): string
    {
        $tokens = [];

        foreach ($this->shortForms() as $short) {
            $tokens[] = self::MAP[$short]['mime'];
            $tokens[] = '.'.self::MAP[$short]['ext'];
        }

        return implode(',', array_values(array_unique($tokens)));
    }

    /**
     * The directory attachments live in, under the configured disk.
     *
     * Three copies of this fallback existed — the widget's store, the delivery cleanup and the
     * orphan sweep — and they did not AGREE: an empty string counted as a valid directory in one
     * and mapped to the default in the others, so the cleanup guarded a root the store had never
     * used. One source, like the caps above.
     */
    public function directory(): string
    {
        $directory = $this->config->get('visual-feedback.attachments.directory');

        return is_string($directory) && trim($directory, '/') !== '' ? trim($directory, '/') : 'visual-feedback';
    }

    public function maxFiles(): int
    {
        return $this->configInt('max_files', 5);
    }

    public function maxFileBytes(): int
    {
        return $this->configInt('max_file_size', 5 * 1024 * 1024);
    }

    /**
     * A byte count as the megabyte NUMBER both surfaces show — one rounding, one source.
     *
     * The hint at the field and the rejection message are two different sentences about the same
     * cap, so they must not compute it twice. A second rounding is invisible at a whole binary
     * megabyte and contradicts the first everywhere else: at a 10_000_000-byte cap, precision 0
     * reads "10 MB each" while one decimal says "max 9.5 MB" and the rule enforces 9765 KB.
     */
    public function megabytesFor(int $bytes): string
    {
        return (string) round($bytes / (1024 * 1024), 1);
    }

    /**
     * The per-file cap as the reporter should read it — "5 MB", "5 Mo" in French.
     *
     * Here rather than in a view because this class is the ONE source for the caps: `accept`, the
     * validation rules and this string must never be able to disagree, and a second copy of the
     * number in a template is exactly how they would.
     *
     * NOT `Number::fileSize()`, which hard-codes an English unit array and takes no locale: it
     * renders "5 MB" into the French form whose rejection message correctly says "5 Mo". The unit
     * is a translated line instead, and it is always megabytes — a sub-megabyte cap therefore
     * reads "0.5 MB" rather than "512 KB". That is the deliberate half of the trade: the
     * rejection sentence has always spelled the cap in MB, so a single unit is what lets the two
     * agree at every cap instead of only at the round ones.
     */
    public function maxFileSizeForHumans(): string
    {
        return $this->megabytesFor($this->maxFileBytes())
            .' '.__('visual-feedback::messages.attachments.size_unit');
    }

    /** The per-file cap in kilobytes, for Laravel's `max:` rule (which counts KB for files). */
    public function maxFileKilobytes(): int
    {
        return max(1, (int) floor($this->maxFileBytes() / 1024));
    }

    private function configInt(string $key, int $default): int
    {
        $value = $this->config->get("visual-feedback.attachments.{$key}");

        return is_numeric($value) ? (int) $value : $default;
    }
}
