<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Attachments;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

/**
 * Submit-side attachment validation. It
 * runs on ALREADY-STORED paths (the widget stored the temp uploads and passes paths, so
 * no Livewire type reaches here) and enforces:
 *
 *  - the per-file, total, and file-count caps from config;
 *  - a MIME ALLOWLIST checked against the SERVER-SNIFFED type (finfo on the bytes), never
 *    the client-supplied MIME or the filename extension — a renamed `.exe` is rejected.
 *
 * It returns a list of localized error messages; an empty list means the set is valid.
 */
final readonly class AttachmentValidator
{
    /**
     * The raster formats getimagesize can decode. Only these get the decompression-bomb
     * dimension check; HEIC/HEIF and video pass on byte caps alone (no decode without
     * ext-imagick — a documented exemption, so an iPhone HEIC photo is never rejected
     * collaterally, which "false = reject" for every format would do).
     */
    private const array RASTER_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private Repository $config,
        private FilesystemFactory $storage,
        private AttachmentPolicy $policy,
    ) {}

    /**
     * @param  list<string>  $paths  already-stored storage paths on the configured disk
     * @return list<string> localized error messages (empty = valid)
     */
    public function validate(array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        // Through the policy, not a second copy of the same two fallbacks. The policy is
        // what the PERIMETER is derived from — the Livewire `max:` rule and the HTML accept
        // attribute — and this is the server-side re-check. Two independent copies of the
        // numbers is exactly the perimeter-vs-server disagreement the policy exists to
        // prevent: they agree today, and nothing would have caught the day they stopped.
        $maxFiles = $this->policy->maxFiles();
        $perFile = $this->policy->maxFileBytes();
        $totalCap = $this->configInt('max_total_size', 15 * 1024 * 1024);
        $allowed = $this->policy->mimeTypes();

        $disk = $this->storage->disk($this->diskName());

        $errors = [];

        if (count($paths) > $maxFiles) {
            $errors[] = trans_choice('visual-feedback::messages.attachments.too_many', $maxFiles, ['max' => $maxFiles]);
        }

        $total = 0;

        foreach ($paths as $path) {
            if (! $disk->exists($path)) {
                $errors[] = (string) __('visual-feedback::messages.attachments.missing', ['name' => basename($path)]);

                continue;
            }

            $size = $disk->size($path);
            $total += $size;

            if ($size > $perFile) {
                $errors[] = (string) __('visual-feedback::messages.attachments.too_large', [
                    'name' => basename($path),
                    'max' => $this->megabytes($perFile),
                ]);
            }

            $content = $disk->get($path) ?? '';
            $mime = $this->sniff($content);

            if (! in_array($mime, $allowed, true)) {
                $errors[] = (string) __('visual-feedback::messages.attachments.invalid_type', ['name' => basename($path)]);
            } elseif (in_array($mime, self::RASTER_MIMES, true)) {
                // The SERVER-SNIFFED class picks this branch — a PNG bomb renamed .heic is
                // still classed as PNG here and checked, never waved through on its extension.
                $dimensionError = $this->checkRasterDimensions($content, basename($path));

                if ($dimensionError !== null) {
                    $errors[] = $dimensionError;
                }
            }
        }

        if ($total > $totalCap) {
            $errors[] = (string) __('visual-feedback::messages.attachments.total_too_large', ['max' => $this->megabytes($totalCap)]);
        }

        return $errors;
    }

    /**
     * The server-sniffed MIME type of the bytes (content magic, never the extension).
     *
     * The handle is NOT closed afterwards, and the omission is the point rather than an
     * oversight: finfo_open returns an OBJECT since PHP 8.1, freed when $finfo leaves scope, so
     * finfo_close() has had nothing to do here for three major versions — and PHP 8.5 deprecates
     * the function outright. That deprecation is invisible to this suite: Laravel's error handler
     * routes E_DEPRECATED to the "deprecations" log channel and returns, so PHPUnit's
     * failOnDeprecation never receives it and the 8.5 lane cannot go red on it either. It was a
     * surviving mutant on the removed lines, not a failing test, that named it.
     */
    private function sniff(string $content): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : finfo_buffer($finfo, $content);

        return $mime === false ? '' : $mime;
    }

    /**
     * The decompression-bomb check for a raster image. A `false` decode means a broken or
     * disguised raster (a bomb with a lying header) and is rejected; a real image is
     * rejected when either edge, or the total pixel count, exceeds the configured cap.
     */
    private function checkRasterDimensions(string $content, string $name): ?string
    {
        $info = getimagesizefromstring($content);

        if ($info === false) {
            return (string) __('visual-feedback::messages.attachments.image_too_large', ['name' => $name]);
        }

        $maxDimension = $this->configInt('max_image_dimension', 15_000);
        $maxPixels = $this->configInt('max_image_pixels', 100_000_000);

        if ($info[0] > $maxDimension || $info[1] > $maxDimension || $maxPixels < $info[0] * $info[1]) {
            return (string) __('visual-feedback::messages.attachments.image_too_large', ['name' => $name]);
        }

        return null;
    }

    private function diskName(): string
    {
        $disk = $this->config->get('visual-feedback.attachments.disk');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    /**
     * Through the policy, like every other cap this class reads. The number in the rejection and
     * the number in the hint at the field are the same number about the same limit, and a second
     * rounding here is how they came to disagree.
     */
    private function megabytes(int $bytes): string
    {
        return $this->policy->megabytesFor($bytes);
    }

    private function configInt(string $key, int $default): int
    {
        $value = $this->config->get("visual-feedback.attachments.{$key}");

        return is_numeric($value) ? (int) $value : $default;
    }
}
