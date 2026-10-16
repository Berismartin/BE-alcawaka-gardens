<?php

namespace App\Domains\Reservations\Reservation\DTOs;

/** Partial update: dates, room, party size or requests. */
final readonly class UpdateReservationDTO
{
    public const FIELDS = ['room_id', 'check_in', 'check_out', 'adults', 'children', 'special_requests'];

    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(array_intersect_key($data, array_flip(self::FIELDS)));
    }

    public function changesStay(): bool
    {
        return (bool) array_intersect(['room_id', 'check_in', 'check_out', 'adults', 'children'], array_keys($this->attributes));
    }
}
