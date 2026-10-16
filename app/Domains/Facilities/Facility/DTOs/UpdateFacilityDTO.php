<?php

namespace App\Domains\Facilities\Facility\DTOs;

/**
 * Partial update: only the fields the client sent are present, so a field
 * can be cleared (sent as null) without touching the others.
 */
final readonly class UpdateFacilityDTO
{
    public const FIELDS = ['name', 'access', 'description', 'opening_hours', 'capacity', 'price_ugx', 'is_active'];

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
