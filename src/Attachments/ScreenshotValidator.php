<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Attachments;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/**
 * Server-side screenshot validation. A screenshot that travels its own path bypasses attachment
 * validation entirely: the only limit left is Livewire's 12-MiB default, with no MIME check, and
 * the file is stored under a hardcoded `image/png` label that nothing ever sniffed. Here
 * the stored screenshot goes through the SAME kind of caps as any attachment:
 *
 *  - a real PNG check (finfo on the bytes, never the filename or client MIME);
 *  - the `screenshot.max_bytes` byte cap;
 *  - the image dimension / pixel caps (a PNG is always measurable).
 *
 * The byte cap is the number the browser fits a capture to before the upload (ClientConfig's
 * `maxBytes`), and the canvas-area budget the capture clamps its scale with stays far inside the
 * pixel caps, so a regularly produced capture, even at scale/DPR 2, is both uploadable and valid.
 */
final readonly class ScreenshotValidator
{
    public function __construct(
        private Repository $config,
        private FilesystemFactory $storage,
    ) {}

    /**
     * @return list<string> localized error messages (empty = valid). A null path (no
     *                      screenshot) is valid — the screenshot is optional.
     */
    public function validate(?string $path): array
    {
        if ($path === null) {
            return [];
        }

        $disk = $this->storage->disk($this->diskName());
        $size = $this->storedSize($disk, $path);

        if ($size === null) {
            return [(string) __('visual-feedback::messages.attachments.screenshot_invalid')];
        }

        if ($size > $this->configInt('screenshot.max_bytes', 8 * 1024 * 1024)) {
            // One reason, as for a non-PNG below: the size already refuses the capture, so its
            // bytes are not downloaded to look for a second one.
            return [(string) __('visual-feedback::messages.attachments.screenshot_too_large')];
        }

        $content = $disk->get($path) ?? '';

        if ($this->sniff($content) !== 'image/png') {
            // A non-PNG screenshot is rejected outright — no dimension check on a lie.
            return [(string) __('visual-feedback::messages.attachments.screenshot_invalid')];
        }

        if ($this->exceedsPixelCaps($content)) {
            return [(string) __('visual-feedback::messages.attachments.screenshot_too_large')];
        }

        return [];
    }

    /**
     * The stored capture's size in bytes, or null when the disk has no size to report for it.
     *
     * The twin of AttachmentValidator::storedSize(): one request answers whether the file is there
     * and how big it is, and a disk reports a file it cannot find by refusing its size, which
     * Flysystem throws as a RuntimeException.
     */
    private function storedSize(Filesystem $disk, string $path): ?int
    {
        try {
            return $disk->size($path);
        } catch (RuntimeException) {
            return null;
        }
    }

    /** Whether the PNG's dimensions or pixel count exceed the configured caps. */
    private function exceedsPixelCaps(string $content): bool
    {
        $info = getimagesizefromstring($content);

        if ($info === false) {
            return true;
        }

        $maxDimension = $this->configInt('attachments.max_image_dimension', 15_000);
        $maxPixels = $this->configInt('attachments.max_image_pixels', 100_000_000);

        return $info[0] > $maxDimension || $info[1] > $maxDimension || $maxPixels < $info[0] * $info[1];
    }

    /**
     * The server-sniffed MIME type of the bytes (content magic, never the extension).
     *
     * The twin of AttachmentValidator::sniff(), and it carries the same omission for the same
     * reason: the handle is NOT closed. finfo_open returns an OBJECT since PHP 8.1, freed when
     * $finfo leaves scope, so finfo_close() has had nothing to do here for three major versions —
     * and PHP 8.5 deprecates the function outright.
     *
     * That deprecation is invisible to this suite: Laravel's error handler routes E_DEPRECATED to
     * the "deprecations" log channel and returns, so phpunit.xml.dist's failOnDeprecation never
     * receives it and the php-next lane on 8.5 cannot go red on it either.
     */
    private function sniff(string $content): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : finfo_buffer($finfo, $content);

        return $mime === false ? '' : $mime;
    }

    private function diskName(): string
    {
        return new AttachmentPolicy($this->config)->disk();
    }

    private function configInt(string $key, int $default): int
    {
        $value = $this->config->get("visual-feedback.{$key}");

        return is_numeric($value) ? (int) $value : $default;
    }
}
