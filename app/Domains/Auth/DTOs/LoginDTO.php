<?php

namespace App\Domains\Auth\DTOs;

final readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            email: strtolower($data['email']),
            password: $data['password'],
            deviceName: $data['device_name'] ?? 'web',
        );
    }
}
