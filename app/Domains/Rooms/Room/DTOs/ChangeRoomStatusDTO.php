<?php

namespace App\Domains\Rooms\Room\DTOs;

use App\Domains\Rooms\Room\Enums\RoomStatus;

final readonly class ChangeRoomStatusDTO
{
    public function __construct(
        public RoomStatus $status,
        public ?string $note = null,
        public bool $assigneeSent = false,
        public ?int $assignedTo = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            status: RoomStatus::from($data['status']),
            note: isset($data['note']) ? trim($data['note']) ?: null : null,
            assigneeSent: array_key_exists('assigned_to', $data),
            assignedTo: isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
        );
    }
}
