<?php

namespace App\Actions\Auth;

use App\Domains\Auth\Services\AuthService;

class SendResetLinkAction
{
    public function __construct(private AuthService $service) {}

    public function execute(string $email): void
    {
        $this->service->sendResetLink($email);
    }
}
