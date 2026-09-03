<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Contracts;

/**
 * A marker for a delivery channel that PERSISTS the report and its attachments beyond the
 * moment of delivery — the database channel is the built-in example: an admin opens a stored
 * report and expects its screenshot to still be there weeks later.
 *
 * The storage-lifecycle refcount therefore EXCLUDES a retaining channel: when
 * any channel retains the report, the attachments are never auto-deleted after delivery —
 * they are owned by retention and prune, which delete the row and its files
 * together when the report ages out. Only when EVERY active channel is transient (mail,
 * webhook — they need the files just long enough to send) does the refcount delete the files
 * once the last transient delivery settles. A custom channel that stores the report for later
 * viewing implements this so its attachments are not swept out from under it.
 */
interface RetainsReport {}
