<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Reporter;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Support\Arrayable;
use Pushery\VisualFeedback\Contracts\ResolvesReporter;
use Pushery\VisualFeedback\Data\Reporter;

/**
 * Default reporter resolver: maps the authenticated user (via the auth guard) to a
 * neutral Reporter DTO, or builds a guest reporter from the submitted form fields. The
 * host User model never leaves this boundary. Bind a custom ResolvesReporter to enrich
 * (team, tenant, display name, …).
 */
final readonly class GuardReporterResolver implements ResolvesReporter
{
    public function __construct(private AuthFactory $auth) {}

    public function resolve(?string $guestName = null, ?string $guestEmail = null, ?string $guestPhone = null): Reporter
    {
        $user = $this->auth->guard()->user();

        if (! $user instanceof Authenticatable) {
            return Reporter::guest($guestName, $guestEmail, $guestPhone);
        }

        $identifier = $user->getAuthIdentifier();

        return Reporter::authenticated(
            id: is_scalar($identifier) ? (string) $identifier : '',
            name: $this->attribute($user, 'name'),
            email: $this->attribute($user, 'email'),
        );
    }

    /**
     * Best-effort read of a common user attribute without coupling to a concrete
     * model. Only a scalar attribute is used; anything else degrades to null.
     */
    private function attribute(Authenticatable $user, string $key): ?string
    {
        if (! $user instanceof Arrayable) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $user->toArray();
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
