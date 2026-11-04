<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Data;

/**
 * Who filed a report — a neutral value object, never the host's User model. The host
 * User is never serialized into a queued channel or mailable; the reporter travels as
 * plain scalars. `id` is a string so it tolerates int / uuid / ulid primary keys, and
 * is null for a guest.
 *
 * A signed-in reporter can be shown the identity fields too, prefilled from the account, where a
 * widget asks for them (`fields: ['signed_in' => true]`), as a contact form does. A name or an
 * address changed there is where a reply goes, `replyName` and `replyEmail`, while the account stays
 * the reporter: the mail's Reply-To follows them, the stored row and the webhook payload keep the
 * account. Both are null for a guest, whose fields are the identity itself.
 */
final readonly class Reporter
{
    public function __construct(
        public ?string $id,
        public ?string $name,
        public ?string $email,
        public bool $isGuest,
        public ?string $phone = null,
        public ?string $replyName = null,
        public ?string $replyEmail = null,
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
     * This signed-in reporter with what the identity fields of the form say: the phone, and the name
     * and the address a reply goes to where they differ from the account's. An empty field keeps the
     * account's value.
     */
    public function withContact(?string $name, ?string $email, ?string $phone): self
    {
        $changed = static function (?string $given, ?string $own): ?string {
            $given = is_string($given) ? trim($given) : '';

            return $given !== '' && $given !== $own ? $given : null;
        };

        $phone = is_string($phone) ? trim($phone) : '';

        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            isGuest: $this->isGuest,
            phone: $phone !== '' ? $phone : $this->phone,
            replyName: $changed($name, $this->name),
            replyEmail: $changed($email, $this->email),
        );
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
