<?php

namespace App\Domains\Rooms\RoomType\DTOs;

/** Partial update: only the fields the client sent. */
final readonly class UpdateRoomTypeDTO
{
    public const FIELDS = ['name', 'base_rate_ugx', 'max_guests', 'description', 'bed_type', 'features', 'is_active'];

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
