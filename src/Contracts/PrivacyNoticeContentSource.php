<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Contracts;

use Illuminate\Contracts\Support\Htmlable;

/**
 * A notice source that supplies the text of the notice itself, which the form shows beside the
 * checkbox, not only a link to a page.
 *
 * Kept separate from PrivacyNoticeSource for the reason PrivacyNoticeWordingSource is: a host that
 * has nothing but a privacy URL implements the base contract and is done. A source that answers here
 * makes the acknowledgment informed without a page of its own, so the form asks for the tick with a
 * text and no URL as well: the reporter reads the notice in the form, where a link would have taken
 * them away from it.
 *
 * `$instance` is the name the widget is mounted with, null for a widget without one and for the
 * standalone page, so a contact form and a feedback widget can show different texts. The markup is
 * rendered as it is returned: it is the host's own text, and it never carries what a reporter
 * typed.
 *
 * Both this and `url()` are asked on every render of the form and again when it is submitted. A
 * source that reads them from a database holds the answer for the request itself.
 */
interface PrivacyNoticeContentSource extends PrivacyNoticeSource
{
    /** The text of the notice for this widget, or null when this source has none for it. */
    public function content(?string $instance): ?Htmlable;
}
