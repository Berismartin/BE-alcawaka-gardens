<?php

namespace App\Actions\Guests\Guest;

use App\Domains\Guests\Guest\DTOs\GuestDTO;
use App\Domains\Guests\Guest\Services\GuestService;
use App\Http\Resources\Guests\GuestResource;

class CreateGuestAction
{
    public function __construct(private GuestService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): GuestResource
    {
        return new GuestResource($this->service->create(GuestDTO::fromRequest($input)));
    }
}
