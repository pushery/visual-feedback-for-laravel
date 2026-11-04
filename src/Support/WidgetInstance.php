<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * The name a widget answers to, for a page that carries more than one modal widget.
 *
 * Every trigger dispatches `visual-feedback:open` on the window, and every modal widget on the page
 * listens for it. A widget with an instance name opens only on an open event that names it,
 * `$dispatch('visual-feedback:open', { instance: 'watcher' })`, and a widget without one only on an
 * event that names none, so a page-wide trigger opens the page-wide widget alone. Its WireKit modal
 * is named after it too, because the kit opens a modal by name, and two modals of one name open
 * together.
 *
 * A name is letters, digits, `-` and `_`, up to 64 of them. Anything else is no name, so it can go
 * into an attribute, an Alpine expression and a modal name as it is.
 */
final class WidgetInstance
{
    /** The modal name of a widget without an instance name, the one the kit's modal always had. */
    public const string MODAL = 'visual-feedback';

    /** An instance name the views and the bundle can carry as it is, or null. */
    public static function name(mixed $instance): ?string
    {
        return is_string($instance) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', trim($instance)) === 1 ? trim($instance) : null;
    }

    /** The Alpine expression a trigger dispatches to open the widget of this instance, or the page-wide one. */
    public static function openExpression(mixed $instance): string
    {
        $name = self::name($instance);

        if ($name === null) {
            return "\$dispatch('visual-feedback:open')";
        }

        return "\$dispatch('visual-feedback:open', { instance: '{$name}' })";
    }

    /** The name of the WireKit modal of the widget of this instance. */
    public static function modalName(mixed $instance): string
    {
        $name = self::name($instance);

        return $name === null ? self::MODAL : self::MODAL.'-'.$name;
    }
}
