<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Console\Concerns;

use Illuminate\Contracts\Config\Repository as Config;
use Pushery\VisualFeedback\Attachments\AttachmentPolicy;

/**
 * Shared storage resolution for the retention commands: the reports table name,
 * the attachments disk, and the safe extraction of a row's attachment paths from its JSON. Both
 * `visual-feedback:prune` and `visual-feedback:forget` delete a row's files before the row, and
 * both read the same columns, so the resolution lives here once.
 */
trait ResolvesReportStorage
{
    private function reportsTable(Config $config): string
    {
        $table = $config->get('visual-feedback.database.table');

        return is_string($table) && $table !== '' ? $table : 'visual_feedback_reports';
    }

    private function attachmentsDisk(Config $config): ?string
    {
        $disk = $config->get('visual-feedback.attachments.disk');

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    /** The package's own attachments directory — the ONLY tree the orphan sweep is allowed to touch. */
    private function attachmentsDirectory(Config $config): string
    {
        // One source for the root — see AttachmentPolicy::directory(). $config stays in the
        // signature because the other resolvers here take it and the symmetry is worth more than
        // the parameter.
        return app(AttachmentPolicy::class)->directory();
    }

    /** @return list<string> the stored attachment paths from a row's `attachments` JSON. */
    private function attachmentPaths(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? array_values(array_filter($decoded, is_string(...))) : [];
    }
}
