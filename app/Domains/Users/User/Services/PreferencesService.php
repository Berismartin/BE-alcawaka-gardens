<?php

namespace App\Domains\Users\User\Services;

use App\Domains\Users\User\DTOs\PreferencesDTO;
use App\Domains\Users\User\Repositories\UserRepositoryInterface;
use App\Models\User;

class PreferencesService
{
    public function __construct(private UserRepositoryInterface $users) {}

    /** @return array<string, mixed> */
    public function update(User $user, PreferencesDTO $dto): array
    {
        $merged = [...PreferencesDTO::resolve($user->preferences), ...$dto->values];
        $this->users->savePreferences($user, $merged);

        return $merged;
    }
}
