<?php

namespace App\Actions\Guests\Guest;

use App\Domains\Guests\Guest\DTOs\UpdateGuestDTO;
use App\Domains\Guests\Guest\Services\GuestService;
use App\Http\Resources\Guests\GuestResource;

class UpdateGuestAction
{
    public function __construct(private GuestService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(string $id, array $input): GuestResource
    {
        return new GuestResource($this->service->update($id, UpdateGuestDTO::fromRequest($input)));
    }
}
