<?php

namespace App\Domains\Rooms\RoomType\DTOs;

final readonly class CreateRoomTypeDTO
{
    /** @param list<string> $features */
    public function __construct(
        public string $name,
        public int $baseRateUgx,
        public int $maxGuests = 2,
        public ?string $description = null,
        public ?string $bedType = null,
        public array $features = [],
        public bool $isActive = true,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            baseRateUgx: (int) $data['base_rate_ugx'],
            maxGuests: (int) ($data['max_guests'] ?? 2),
            description: $data['description'] ?? null,
            bedType: $data['bed_type'] ?? null,
            features: array_values($data['features'] ?? []),
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'base_rate_ugx' => $this->baseRateUgx,
            'max_guests' => $this->maxGuests,
            'description' => $this->description,
            'bed_type' => $this->bedType,
            'features' => $this->features,
            'is_active' => $this->isActive,
        ];
    }
}
