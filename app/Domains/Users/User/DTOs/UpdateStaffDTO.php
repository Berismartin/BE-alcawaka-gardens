<?php

namespace App\Domains\Users\User\DTOs;

/** Partial update: name, role, active flag. */
final readonly class UpdateStaffDTO
{
    public const FIELDS = ['name', 'role', 'is_active'];

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
