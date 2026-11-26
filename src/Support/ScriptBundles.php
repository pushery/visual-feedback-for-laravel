<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * The widget's classic scripts as the package serves them itself, under an address that carries a digest of
 * all four.
 *
 * A file a web server hands out from `public/` answers whatever the query asks for: a server that is still on
 * the previous release serves its own copy under the address of a newer page, with a 200, and a cache in front
 * of it may keep that copy under the new address. Served through this route, the address names the bundles by
 * a digest of their bytes, and a server that does not hold those bytes answers with `no-cache`, so nothing
 * keeps its copy under a name that is not its own.
 *
 * One digest covers all four files, so the renderer and the editor sit in the same directory as the capture
 * bundle that appends them, which is where the loader looks for them: it takes the directory of its own
 * address and puts the sibling's file name in it.
 */
final readonly class ScriptBundles
{
    /** The name of the route that serves the scripts. */
    public const string ROUTE = 'visual-feedback.script';

    /** The value of `ui.scripts` that has the pages load the scripts from that route. */
    public const string ROUTED = 'route';

    /**
     * The files the route serves: the two a page's tags name, then the renderer and the editor, which the
     * capture bundle appends from beside itself.
     */
    public const array FILES = [
        'visual-feedback-widget.iife.js',
        'visual-feedback.iife.js',
        'visual-feedback-renderer.iife.js',
        'visual-feedback-editor.iife.js',
    ];

    public function __construct(
        private Repository $config,
        private UrlGenerator $urls,
    ) {}

    /**
     * Whether a page loads the scripts from the route: `ui.scripts` asks for it, and no `ui.assets` names a base
     * URL of its own, which wins as it always did.
     */
    public function served(): bool
    {
        $base = $this->config->get('visual-feedback.ui.assets');

        return (! is_string($base) || $base === '') && $this->config->get('visual-feedback.ui.scripts') === self::ROUTED;
    }

    /** Where the package keeps one of the files. */
    public static function path(string $file): string
    {
        return dirname(__DIR__, 2).'/dist/'.$file;
    }

    /** Sixteen hexadecimal digits of a digest over the four files, the part of the address that moves with them. */
    public static function digest(): string
    {
        $lines = [];

        foreach (self::FILES as $file) {
            $lines[] = $file.' '.hash_file('xxh128', self::path($file));
        }

        return substr(hash('xxh128', implode("\n", $lines)), 0, 16);
    }

    /** Where the route serves one of the files. */
    public function url(string $file): string
    {
        return $this->urls->route(self::ROUTE, ['version' => self::digest(), 'file' => $file]);
    }
}
