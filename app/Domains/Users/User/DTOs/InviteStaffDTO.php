<?php

namespace App\Domains\Users\User\DTOs;

use App\Domains\Users\User\Enums\UserRole;

final readonly class InviteStaffDTO
{
    public function __construct(public string $name, public string $email, public UserRole $role) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(trim($data['name']), strtolower(trim($data['email'])), UserRole::from($data['role']));
    }
}
