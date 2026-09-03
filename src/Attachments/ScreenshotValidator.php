<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Attachments;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

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
 * The caps that gate this are the same config values that feed the client downscale, so a
 * regularly produced capture — even at scale/DPR 2 — is always both uploadable and valid.
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

        if (! $disk->exists($path)) {
            return [(string) __('visual-feedback::messages.attachments.screenshot_invalid')];
        }

        $errors = [];

        $maxBytes = $this->configInt('screenshot.max_bytes', 8 * 1024 * 1024);

        if ($disk->size($path) > $maxBytes) {
            $errors[] = (string) __('visual-feedback::messages.attachments.screenshot_too_large');
        }

        $content = $disk->get($path) ?? '';

        if ($this->sniff($content) !== 'image/png') {
            // A non-PNG screenshot is rejected outright — no dimension check on a lie.
            $errors[] = (string) __('visual-feedback::messages.attachments.screenshot_invalid');

            return $errors;
        }

        if ($this->exceedsPixelCaps($content)) {
            $errors[] = (string) __('visual-feedback::messages.attachments.screenshot_too_large');
        }

        return $errors;
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
     * That deprecation is invisible to this suite, which is why it survived so long: Laravel's
     * error handler routes E_DEPRECATED to the "deprecations" log channel and returns, so
     * phpunit.xml.dist's failOnDeprecation never receives it and the php-next lane on 8.5 cannot
     * go red on it either. Eight surviving mutants on these lines named it — a failing test never
     * could have.
     */
    private function sniff(string $content): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : finfo_buffer($finfo, $content);

        return $mime === false ? '' : $mime;
    }

    private function diskName(): string
    {
        $disk = $this->config->get('visual-feedback.attachments.disk');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    private function configInt(string $key, int $default): int
    {
        $value = $this->config->get("visual-feedback.{$key}");

        return is_numeric($value) ? (int) $value : $default;
    }
}
