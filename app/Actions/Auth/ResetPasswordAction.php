<?php

namespace App\Actions\Auth;

use App\Domains\Auth\DTOs\SetPasswordDTO;
use App\Domains\Auth\Services\AuthService;

class ResetPasswordAction
{
    public function __construct(private AuthService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): void
    {
        $this->service->resetPassword(SetPasswordDTO::fromRequest($input));
    }
}
