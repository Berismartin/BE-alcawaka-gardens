<?php

namespace App\Domains\Rooms\Room\DTOs;

/** Partial update of a room's setup (number, floor, type). Status changes go through ChangeRoomStatusDTO. */
final readonly class UpdateRoomDTO
{
    public const FIELDS = ['number', 'floor', 'room_type_id'];

    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(array_intersect_key($data, array_flip(self::FIELDS)));
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->attributes);
    }
}
