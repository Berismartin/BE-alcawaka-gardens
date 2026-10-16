<?php

namespace App\Domains\Users\User\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    /** @param array<string, mixed> $preferences */
    public function savePreferences(User $user, array $preferences): User;
}
