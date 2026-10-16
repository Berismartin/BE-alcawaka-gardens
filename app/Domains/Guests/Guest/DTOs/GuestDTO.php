<?php

namespace App\Domains\Guests\Guest\DTOs;

use App\Domains\Guests\Guest\ValueObjects\PhoneNumber;

final readonly class GuestDTO
{
    public function __construct(
        public string $fullName,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $nationality = null,
        public ?string $idNumber = null,
        public ?string $notes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            fullName: trim($data['full_name']),
            phone: PhoneNumber::normalize($data['phone'] ?? null),
            email: isset($data['email']) ? strtolower(trim($data['email'])) ?: null : null,
            nationality: $data['nationality'] ?? null,
            idNumber: $data['id_number'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'full_name' => $this->fullName,
            'phone' => $this->phone,
            'email' => $this->email,
            'nationality' => $this->nationality,
            'id_number' => $this->idNumber,
            'notes' => $this->notes,
        ];
    }
}
