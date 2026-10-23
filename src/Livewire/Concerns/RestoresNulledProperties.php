<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Livewire\Concerns;

use ReflectionProperty;

/**
 * Gives a typed property its default back after a request wrote `null` into it.
 *
 * Livewire sets a typed property that does not take `null` by unsetting it, and calls the `updated…`
 * hooks only once every update of the request is applied. A hook, an action or the render that read
 * the property afterwards threw an Error, which nothing catches: the request answered 500 and the
 * host's error tracker received a report, as often as anyone sent it. A component calls this from its
 * `updated()` hook, which Livewire runs before the hook of the property, so every later reader meets
 * a value again.
 */
trait RestoresNulledProperties
{
    /**
     * The property a request path names, back at its default where Livewire unset it. Answers the
     * property's name where it gave a default back, and null where there was nothing to give back.
     */
    private function restoreANulledProperty(string $path): ?string
    {
        $name = explode('.', $path, 2)[0];
        $reflection = new ReflectionProperty($this, $name);

        if ($reflection->isInitialized($this) || ! $reflection->hasDefaultValue()) {
            return null;
        }

        $reflection->setValue($this, $reflection->getDefaultValue());

        return $name;
    }
}
