<?php

namespace App\Domains\Rooms\Room\DTOs;

use App\Domains\Rooms\Room\Enums\RoomStatus;

final readonly class CreateRoomDTO
{
    public function __construct(
        public string $number,
        public string $roomTypeId,
        public ?int $floor = null,
        public RoomStatus $status = RoomStatus::Available,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            number: trim($data['number']),
            roomTypeId: $data['room_type_id'],
            floor: isset($data['floor']) ? (int) $data['floor'] : null,
            status: isset($data['status']) ? RoomStatus::from($data['status']) : RoomStatus::Available,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['number' => $this->number, 'room_type_id' => $this->roomTypeId, 'floor' => $this->floor, 'status' => $this->status->value];
    }
}
