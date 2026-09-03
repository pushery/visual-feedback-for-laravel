<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Attachments;

use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Removes the per-file subdirectory an attachment leaves behind once its file is gone.
 *
 * Every stored attachment gets its own random subdirectory (`<root>/<hash>/<name>`), and
 * `Storage::delete()` removes files, never their parent. So both deletion paths — the
 * post-delivery cleanup in ReportDeliveryTracker and the orphan sweep — used to leave one empty
 * directory per report, permanently. Cheap individually, and it compounds twice: unbounded inode
 * growth on a local disk, and the sweep's own `allFiles()` walk gets slower with every report it
 * cleans up, so the command meant to keep the disk tidy degrades as it works.
 *
 * Two invariants, and the second is the important one:
 *
 * - a directory is removed only when it holds NOTHING — no files and no subdirectories. A report
 *   may carry several attachments, and while each lives in its own hash directory, "looks empty"
 *   must never be inferred from the one path we happened to delete.
 * - the configured ROOT is never removed, however empty it gets. It is the consumer's directory,
 *   possibly shared, and re-creating it is not this class's business. Nor is anything outside it:
 *   a path that does not sit under the root is skipped rather than trusted.
 */
final readonly class EmptyDirectoryPruner
{
    /**
     * Prune the parent directory of each deleted path, where it is now empty.
     *
     * @param  list<string>  $deletedPaths  paths whose files have already been removed
     */
    public function prune(Filesystem $disk, array $deletedPaths, string $root): void
    {
        $root = trim($root, '/');
        $seen = [];

        foreach ($deletedPaths as $path) {
            // Separators FIRST, then dirname. On a Unix host dirname() treats a backslash as an
            // ordinary character, so a Windows-style path would come back as '.' and the
            // directory would never be pruned — which is what the guard for this caught.
            $directory = trim(dirname(str_replace('\\', '/', $path)), '/');
            // dirname() of a bare filename is '.', and a path directly in the root has the root
            // itself as its parent — neither is ours to remove.
            if ($directory === '') {
                continue;
            }
            if ($directory === '.') {
                continue;
            }
            if ($directory === $root) {
                continue;
            }

            // Only inside the root. A path from somewhere else is a caller mistake, and deleting
            // by it would be the kind of mistake that takes a directory with it.
            if ($root !== '' && ! str_starts_with($directory.'/', $root.'/')) {
                continue;
            }

            if (isset($seen[$directory])) {
                continue;
            }

            $seen[$directory] = true;

            if ($disk->files($directory) === [] && $disk->directories($directory) === []) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
