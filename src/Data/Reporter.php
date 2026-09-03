<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Data;

/**
 * Who filed a report — a neutral value object, NEVER the host's User model. The host
 * User is never serialized into a queued channel or mailable; the reporter travels as
 * plain scalars. `id` is a string so it tolerates int / uuid / ulid primary keys, and
 * is null for a guest.
 */
final readonly class Reporter
{
    public function __construct(
        public ?string $id,
        public ?string $name,
        public ?string $email,
        public bool $isGuest,
        public ?string $phone = null,
    ) {}

    public static function guest(?string $name, ?string $email, ?string $phone = null): self
    {
        return new self(id: null, name: $name, email: $email, isGuest: true, phone: $phone);
    }

    public static function authenticated(string $id, ?string $name, ?string $email): self
    {
        return new self(id: $id, name: $name, email: $email, isGuest: false);
    }

    /**
     * @return array{id: ?string, name: ?string, email: ?string, is_guest: bool, phone: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_guest' => $this->isGuest,
            'phone' => $this->phone,
        ];
    }
}
