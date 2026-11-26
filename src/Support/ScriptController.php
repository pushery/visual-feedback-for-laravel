<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\Http\Response;

/**
 * Serves one of the widget's classic scripts out of the installed package.
 *
 * A request for the current digest may be cached for a year, because changed scripts are linked under a new
 * one. A request for any other digest comes from a page that another release rendered: on a server that is
 * still on the previous release it names the next one, and after an upgrade it names the one before. Either way
 * it gets the scripts this server holds and no lasting cache, so its address never keeps a copy that is not
 * its own.
 *
 * The route admits only the four names in ScriptBundles::FILES, and it runs outside the `web` group, so the
 * answer sets no cookie and a shared cache may keep it.
 */
final class ScriptController
{
    public function __invoke(string $version, string $file): Response
    {
        return new Response((string) file_get_contents(ScriptBundles::path($file)), 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => $version === ScriptBundles::digest() ? 'public, max-age=31536000, immutable' : 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
