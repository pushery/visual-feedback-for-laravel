<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Contracts;

use Pushery\VisualFeedback\Data\ReportContextEntry;

/**
 * Contributes structured context entries to every report. Register providers globally
 * in `config('visual-feedback.context_providers')`; per-instance context is passed as
 * widget mount props instead.
 *
 * AUTHORIZATION IS THE HOST'S DUTY. A provider must authorize what it exposes — return
 * only entries the current request's user is allowed to see. The context path is
 * server-side only: there is no client-callable action that sets context, so a
 * provider's `entries()` is the single trusted source (the structural fix for a
 * defect where one of two context-setting paths authorized and the other did not).
 */
interface ReportContextProvider
{
    /**
     * @return list<ReportContextEntry>
     */
    public function entries(): array;
}
