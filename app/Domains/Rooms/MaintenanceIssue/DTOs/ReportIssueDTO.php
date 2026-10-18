<?php

namespace App\Domains\Rooms\MaintenanceIssue\DTOs;

use App\Domains\Rooms\Room\Enums\RoomStatus;

final readonly class ReportIssueDTO
{
    /** @param ?RoomStatus $closeAs under_maintenance or out_of_order closes the room; null keeps it in use */
    public function __construct(
        public string $roomId,
        public string $description,
        public ?RoomStatus $closeAs = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            roomId: $data['room_id'],
            description: trim($data['description']),
            closeAs: ($data['close_as'] ?? null) ? RoomStatus::from($data['close_as']) : null,
        );
    }
}
