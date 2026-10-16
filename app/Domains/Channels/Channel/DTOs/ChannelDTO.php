<?php

namespace App\Domains\Channels\Channel\DTOs;

final readonly class ChannelDTO
{
    public function __construct(
        public string $name,
        public string $roomId,
        public ?string $importUrl = null,
        public bool $isActive = true,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(trim($data['name']), $data['room_id'], $data['import_url'] ?? null, (bool) ($data['is_active'] ?? true));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'room_id' => $this->roomId, 'import_url' => $this->importUrl, 'is_active' => $this->isActive];
    }
}
