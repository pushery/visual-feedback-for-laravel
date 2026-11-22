<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\Http\Response;

/**
 * Serves the widget's stylesheet as a file.
 *
 * The address names the sheet by a digest of its text. A request for the current digest may be cached
 * for a year, because a changed sheet is linked under a new one. A request for any other digest comes
 * from a page rendered before the sheet changed: it gets the current sheet, which that page now needs,
 * and no lasting cache, so its address never keeps an old copy alive.
 *
 * The route runs outside the `web` group, so the answer sets no cookie and a shared cache may keep it.
 */
final class StylesheetController
{
    public function __invoke(Stylesheet $stylesheet, string $version): Response
    {
        $css = $stylesheet->css();

        return new Response($css, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => $version === Stylesheet::digest($css) ? 'public, max-age=31536000, immutable' : 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
