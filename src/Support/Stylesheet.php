<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\View\Factory;

/**
 * The widget's stylesheet as one text, and the address the package serves it under.
 *
 * The text depends on the view tree that is served and on the dark-mode keys, so it is rendered rather than
 * shipped as a file. The address carries a digest of that text, so a browser may keep its copy for as long as
 * it likes: a changed sheet has a new address.
 *
 * Nothing is kept between two calls. The include renders the sheet once for its link or its block, as it
 * always rendered it once for the page, and a changed tree or configuration is in the next answer.
 */
final readonly class Stylesheet
{
    /** The name of the route that serves the sheet. */
    public const string ROUTE = 'visual-feedback.stylesheet';

    public function __construct(
        private Factory $views,
        private UrlGenerator $urls,
        private Settings $settings,
        private ServedViewTree $tree,
    ) {}

    /**
     * The rules of the tree that serves, as the route and an inline block write them.
     *
     * The WireKit tree gets a smaller sheet rather than none: most of the plain tree's rules would fight the
     * application's design tokens, but tokens position no floating panel, style no dialog and conceal no
     * honeypot. The choice asks which template the widget resolves to, not which tree is configured. The two
     * differ after the documented umbrella publish, which copies the plain templates in front of the package's
     * paths while `ui.variant` still says wirekit, and the configured answer there left a plain widget without
     * its sheet.
     */
    public function css(): string
    {
        $view = $this->tree->servingWireKit() ? 'visual-feedback::stylesheet-wirekit' : 'visual-feedback::stylesheet';

        return trim($this->views->make($view)->render())."\n";
    }

    /** Sixteen hexadecimal digits of a digest over a sheet, the part of its address that moves with it. */
    public static function digest(string $css): string
    {
        return substr(hash('xxh128', $css), 0, 16);
    }

    /** Where the current sheet is served. */
    public function url(): string
    {
        return $this->urls->route(self::ROUTE, ['version' => self::digest($this->css())]);
    }

    /**
     * Whether the include writes the sheet into the page instead of linking it.
     *
     * A boolean the include was given decides for that include. Without one, `ui.stylesheet` decides for every
     * include of the application.
     *
     * @param  mixed  $requested  the value of `inline` in the data of the include, which a host writes, so any value
     */
    public function inline(mixed $requested = null): bool
    {
        return is_bool($requested) ? $requested : $this->settings->inlineStylesheet();
    }
}
