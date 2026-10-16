<?php

namespace App\Domains\Users\User\Repositories;

use App\Models\User;

class UserRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function savePreferences(User $user, array $preferences): User
    {
        $user->forceFill(['preferences' => $preferences])->save();

        return $user;
    }
}
