<?php

namespace App\Domains\Facilities\Facility\DTOs;

use App\Domains\Facilities\Facility\Enums\FacilityAccess;

final readonly class CreateFacilityDTO
{
    public function __construct(
        public string $name,
        public FacilityAccess $access,
        public ?string $description = null,
        public ?string $openingHours = null,
        public ?int $capacity = null,
        public ?int $priceUgx = null,
        public bool $isActive = true,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            access: FacilityAccess::from($data['access']),
            description: $data['description'] ?? null,
            openingHours: $data['opening_hours'] ?? null,
            capacity: isset($data['capacity']) ? (int) $data['capacity'] : null,
            priceUgx: isset($data['price_ugx']) ? (int) $data['price_ugx'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'access' => $this->access->value,
            'description' => $this->description,
            'opening_hours' => $this->openingHours,
            'capacity' => $this->capacity,
            'price_ugx' => $this->priceUgx,
            'is_active' => $this->isActive,
        ];
    }
}
