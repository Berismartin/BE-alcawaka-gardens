<?php

namespace App\Actions\Guests\Guest;

use App\Domains\Guests\Guest\Services\GuestService;
use App\Http\Resources\Guests\GuestResource;

class ShowGuestAction
{
    public function __construct(private GuestService $service) {}

    public function execute(string $id): GuestResource
    {
        return new GuestResource($this->service->find($id));
    }
}
