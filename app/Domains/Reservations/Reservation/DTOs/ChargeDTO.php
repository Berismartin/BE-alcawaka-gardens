<?php

namespace App\Domains\Reservations\Reservation\DTOs;

use App\Domains\Reservations\Reservation\Enums\ChargeCategory;

final readonly class ChargeDTO
{
    public function __construct(
        public ChargeCategory $category,
        public string $description,
        public int $amountUgx,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(ChargeCategory::from($data['category']), trim($data['description']), (int) $data['amount_ugx']);
    }
}
