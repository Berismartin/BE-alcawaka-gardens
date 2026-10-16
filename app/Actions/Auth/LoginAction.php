<?php

namespace App\Actions\Auth;

use App\Domains\Auth\DTOs\LoginDTO;
use App\Domains\Auth\Services\AuthService;
use App\Http\Resources\Users\UserResource;

class LoginAction
{
    public function __construct(private AuthService $service) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{user: UserResource, token: string}
     */
    public function execute(array $input): array
    {
        $result = $this->service->login(LoginDTO::fromRequest($input));

        return ['user' => new UserResource($result['user']), 'token' => $result['token']];
    }
}
