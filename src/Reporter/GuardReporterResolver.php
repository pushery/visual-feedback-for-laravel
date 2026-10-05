<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Reporter;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Pushery\VisualFeedback\Contracts\ResolvesReporter;
use Pushery\VisualFeedback\Data\Reporter;
use Stringable;

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
     * model. A scalar, or a value that converts to a string, is used; anything else
     * degrades to null.
     *
     * The attribute is read, not serialized. An Eloquent model's toArray() honors `$hidden` and
     * `$visible`, so a host that keeps `email` out of its JSON responses would lose the reply
     * address, the stored address and every erasure by address; it also computes every `$appends`
     * accessor and serializes the loaded relations, on each page that renders the widget. Reading
     * through data_get() asks the model for the attribute alone, and any other user through
     * ArrayAccess, a public property or `__get()`, which is how Laravel's GenericUser answers.
     *
     * Two shapes still need a serialization, because nothing else answers them. A cast whose value
     * becomes text only when the model serializes it is read from a copy of the model that hides,
     * limits and appends nothing, so the user the host holds is left as it was. A user outside
     * Eloquent that keeps its state private behind toArray() is read through that array.
     */
    private function attribute(Authenticatable $user, string $key): ?string
    {
        $value = data_get($user, $key);

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if ($user instanceof Model) {
            if (is_object($value)) {
                $value = (clone $user)->setHidden([])->setVisible([])->setAppends([])->attributesToArray()[$key] ?? null;
            }
        } elseif (($value === null || is_object($value)) && $user instanceof Arrayable) {
            /** @var array<string, mixed> $data */
            $data = $user->toArray();
            $value = $data[$key] ?? null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
