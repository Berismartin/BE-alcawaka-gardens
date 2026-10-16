<?php

namespace App\Actions\Guests\Guest;

use App\Domains\Guests\Guest\Services\GuestService;

class DeleteGuestAction
{
    public function __construct(private GuestService $service) {}

    public function execute(string $id): void
    {
        $this->service->delete($id);
    }
}
