<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\Foundation\Vite;

/**
 * The nonce the widget's script and style tags carry under a nonce-based content security policy.
 *
 * A nonce passed to the tag wins. Without one, the tags carry the nonce the application gave
 * Laravel's Vite integration with `Vite::useCspNonce()`, the same fallback Livewire uses for its
 * own tags, so an application that already nonces Livewire nonces this widget without a change.
 * Without either, the tags carry no nonce.
 */
final readonly class CspNonce
{
    public function __construct(private Vite $vite) {}

    /** The nonce to render, or null when there is none to render. */
    public function resolve(mixed $given = null): ?string
    {
        if (is_string($given) && $given !== '') {
            return $given;
        }

        $nonce = $this->vite->cspNonce();

        return is_string($nonce) && $nonce !== '' ? $nonce : null;
    }
}
