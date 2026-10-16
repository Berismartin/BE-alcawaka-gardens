<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\DTOs\LoginDTO;
use App\Domains\Users\User\Repositories\UserRepositoryInterface;
use App\Exceptions\EntityValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private UserRepositoryInterface $users) {}

    /** @return array{user: User, token: string} */
    public function login(LoginDTO $dto): array
    {
        $user = $this->users->findByEmail($dto->email);

        // Same message for unknown email and wrong password, so emails can't be probed.
        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw new EntityValidationException('These credentials do not match our records.', [
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        return ['user' => $user, 'token' => $user->createToken($dto->deviceName)->plainTextToken];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
