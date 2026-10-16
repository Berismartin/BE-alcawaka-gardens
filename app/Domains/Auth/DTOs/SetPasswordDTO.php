<?php

namespace App\Domains\Auth\DTOs;

/** Accepting an invitation or resetting a forgotten password: email + one-time token + new password. */
final readonly class SetPasswordDTO
{
    public function __construct(public string $email, public string $token, public string $password) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(strtolower(trim($data['email'])), $data['token'], $data['password']);
    }
}
