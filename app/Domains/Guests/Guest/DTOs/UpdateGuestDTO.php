<?php

namespace App\Domains\Guests\Guest\DTOs;

use App\Domains\Guests\Guest\ValueObjects\PhoneNumber;

/** Partial update: only the fields the client sent. */
final readonly class UpdateGuestDTO
{
    public const FIELDS = ['full_name', 'phone', 'email', 'nationality', 'id_number', 'notes'];

    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        $attributes = array_intersect_key($data, array_flip(self::FIELDS));
        if (array_key_exists('phone', $attributes)) {
            $attributes['phone'] = PhoneNumber::normalize($attributes['phone']);
        }
        if (array_key_exists('email', $attributes)) {
            $attributes['email'] = $attributes['email'] ? strtolower(trim($attributes['email'])) : null;
        }

        return new self($attributes);
    }
}
