<?php

namespace App\Actions\Reservations\Reservation;

use App\Domains\Reservations\Reservation\DTOs\StayDTO;
use App\Domains\Reservations\Reservation\Services\AvailabilityService;
use App\Http\Resources\Reservations\AvailableRoomResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SearchAvailabilityAction
{
    public function __construct(private AvailabilityService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): AnonymousResourceCollection
    {
        return AvailableRoomResource::collection($this->service->search(StayDTO::fromRequest($input), $input['except_reservation_id'] ?? null));
    }
}
