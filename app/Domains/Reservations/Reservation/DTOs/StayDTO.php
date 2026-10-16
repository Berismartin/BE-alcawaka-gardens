<?php

namespace App\Domains\Reservations\Reservation\DTOs;

use Carbon\CarbonImmutable;

/** The room and dates being asked for: the input to availability, pricing and booking. */
final readonly class StayDTO
{
    public function __construct(
        public string $checkIn,
        public string $checkOut,
        public int $adults = 1,
        public int $children = 0,
        public ?string $roomTypeId = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            checkIn: $data['check_in'],
            checkOut: $data['check_out'],
            adults: (int) ($data['adults'] ?? 1),
            children: (int) ($data['children'] ?? 0),
            roomTypeId: $data['room_type_id'] ?? null,
        );
    }

    public function guests(): int
    {
        return $this->adults + $this->children;
    }

    public function nights(): int
    {
        return (int) CarbonImmutable::parse($this->checkIn)->diffInDays(CarbonImmutable::parse($this->checkOut));
    }
}
