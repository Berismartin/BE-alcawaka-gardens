<?php

namespace App\Domains\Rates\SeasonalDiscount\DTOs;

/** Create and update share every field (the form always sends them all). */
final readonly class SeasonalDiscountDTO
{
    public function __construct(
        public string $name,
        public string $startsOn,
        public string $endsOn,
        public int $percent,
        public ?string $roomTypeId = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            startsOn: $data['starts_on'],
            endsOn: $data['ends_on'],
            percent: (int) $data['percent'],
            roomTypeId: $data['room_type_id'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'percent' => $this->percent,
            'room_type_id' => $this->roomTypeId,
        ];
    }
}
